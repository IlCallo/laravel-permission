<?php

namespace Spatie\Permission\Traits;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Restrictable;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\PermissionRegistrar;

trait HasPermissions
{
    /**
     * A model may have multiple direct permissions.
     * @return BelongsToMany|Relation
     */
    abstract public function permissions(): BelongsToMany;

    /**
     * Grant the given permission(s) to a role.
     * If a restrictable instance is given, given permission(s) is/are scoped to it,
     *  otherwise there won't be a scope for the permission(s).
     *
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return $this
     */
    public function givePermissionTo($permissions, Restrictable $restrictable = null)
    {
        // Permission objects, if directly collected, becomes arrays of fields and the flatten() messes with
        // the map function giving every single Permission field as parameter for getStoredPermission.
        // To avoid this, if a Permission is given an empty collection is created and the permission is pushed inside.
        // In this way, in case of a Permission instance, the object is not flattened,
        // but for arrays, collections and string everything works as expected.
        $permissions = (($permissions instanceof Permission) ? collect()->push($permissions) : collect($permissions))
            ->flatten()
            ->map(function ($permission) {
                return $this->getStoredPermission($permission);
            })
            ->each(function ($permission) {
                $this->ensureModelSharesGuard($permission);
            })
            // Attach takes ids, we retrieve them
            ->map(function ($permission) {
                return $permission->id;
            })
            ->all();

        // If there is no restrictable instance, we won't add anything on the pivot table,
        //  which will default to null values on the restrictable morph.
        // Otherwise we set the references to it
        $this->permissions()->attach($permissions, is_null($restrictable) ? [] : [
            'restrictable_id' => $restrictable->getRestrictableId(),
            'restrictable_type' => $restrictable->getRestrictableTable(),
        ]);

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current not scoped permissions and set the given ones.
     * If a Restrictable instance is given, permissions will be removed and set only for that instance scope.
     *
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return $this
     */
    public function syncPermissions($permissions, Restrictable $restrictable = null)
    {
        $this->permissions($restrictable)->detach();

        return $this->givePermissionTo($permissions, $restrictable);
    }

    /**
     * Revoke the given permission.
     * If a Restrictable instance is given, the permission will be removed only for that resource scope.
     *
     * @param \Spatie\Permission\Contracts\Permission|\Spatie\Permission\Contracts\Permission[]|string|string[] $permission
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return $this
     */
    public function revokePermissionTo($permission, Restrictable $restrictable = null)
    {
        $this->permissions($restrictable)->detach($this->getStoredPermission($permission)->id);

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     * @return \Spatie\Permission\Contracts\Permission|\Spatie\Permission\Contracts\Permission[]|\Illuminate\Support\Collection
     */
    protected function getStoredPermission($permissions)
    {
        if (is_string($permissions)) {
            return app(Permission::class)->findByName($permissions, $this->getDefaultGuardName());
        }

        if (is_array($permissions)) {
            return app(Permission::class)
                ->whereIn('name', $permissions)
                ->whereIn('guard_name', $this->getGuardNames())
                ->get();
        }

        return $permissions;
    }

    /**
     * @param \Spatie\Permission\Contracts\Permission|\Spatie\Permission\Contracts\Role $roleOrPermission
     *
     * @throws \Spatie\Permission\Exceptions\GuardDoesNotMatch
     */
    protected function ensureModelSharesGuard($roleOrPermission)
    {
        if (! $this->getGuardNames()->contains($roleOrPermission->guard_name)) {
            throw GuardDoesNotMatch::create($roleOrPermission->guard_name, $this->getGuardNames());
        }
    }

    protected function getGuardNames(): Collection
    {
        if ($this->guard_name) {return collect( $this->guard_name);
    }

        return collect(config('auth.guards'))
            ->map(function ($guard) {
                return config("auth.providers.{$guard['provider']}.model");
            })
            ->filter(function ($model) {
                return get_class($this) === $model;
            })
            ->keys();
    }

    protected function getDefaultGuardName(): string
    {
        $default = config('auth.defaults.guard');

        return $this->getGuardNames()->first() ?: $default;
    }

    /**
     * Forget the cached permissions.
     */
    public function forgetCachedPermissions()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
