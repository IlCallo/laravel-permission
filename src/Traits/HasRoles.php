<?php

namespace Spatie\Permission\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Restrictable;
use Spatie\Permission\Contracts\Role;

trait HasRoles
{
    use HasPermissions;

    public static function bootHasRoles()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->roles()->detach();
            $model->permissions()->detach();
        });
    }

    /**
     * A model may have multiple roles.
     * Roles can be retrieved scoped to a certain restrictable instance.
     *
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return MorphToMany
     */
    public function roles(Restrictable $restrictable = null): MorphToMany
    {
        $rolesForeignKeyName = str_singular(config('permission.table_names.roles')).'_id';

        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            'model_id',
            $rolesForeignKeyName
        )->withPivot('restrictable_id', 'restrictable_type')
            ->wherePivot('restrictable_id', is_null($restrictable) ? null : $restrictable->getRestrictableId())
            ->wherePivot('restrictable_type', is_null($restrictable) ? null : $restrictable->getRestrictableTable());
    }

    /**
     * A model may have multiple direct permissions.
     * Direct permissions can be retrieved scoped to a certain restrictable instance.
     *
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return MorphToMany
     */
    public function permissions(Restrictable $restrictable = null): MorphToMany
    {
        $permissionsForeignKeyName = str_singular(config('permission.table_names.permissions')).'_id';

        return $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_permissions'),
            'model_id',
            $permissionsForeignKeyName
        )->withPivot('restrictable_id', 'restrictable_type')
            ->wherePivot('restrictable_id', is_null($restrictable) ? null : $restrictable->getRestrictableId())
            ->wherePivot('restrictable_type', is_null($restrictable) ? null : $restrictable->getRestrictableTable());
    }

    /**
     * Scope the model query to certain roles only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRole(Builder $query, $roles): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->toArray();
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(function ($role) {
            if ($role instanceof Role) {
                return $role;
            }

            return app(Role::class)->findByName($role, $this->getDefaultGuardName());
        }, $roles);

        return $query->whereHas('roles', function ($query) use ($roles) {
            $query->where(function ($query) use ($roles) {
                foreach ($roles as $role) {
                    $query->orWhere(config('permission.table_names.roles') . '.id', $role->id);
                }
            });
        });
    }

    /**
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return array
     */
    protected function convertToPermissionModels($permissions): array
    {
        if ($permissions instanceof Collection) {
            $permissions = $permissions->toArray();
        }

        $permissions = array_wrap($permissions);

        return array_map(function ($permission) {
            if ($permission instanceof Permission) {
                return $permission;
            }

            return app(Permission::class)->findByName($permission, $this->getDefaultGuardName());
        }, $permissions);
    }

    /**
     * Scope the model query to certain permissions only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePermission(Builder $query, $permissions): Builder
    {
        $permissions = $this->convertToPermissionModels($permissions);

        $rolesWithPermissions = array_unique(array_reduce($permissions, function ($result, $permission) {
            return array_merge($result, $permission->roles->all());
        }, []));

        return $query->
            where(function ($query) use ($permissions, $rolesWithPermissions) {
                $query->whereHas('permissions', function ($query) use ($permissions) {
                    $query->where(function ($query) use ($permissions) {
                        foreach ($permissions as $permission) {
                            $query->orWhere(config('permission.table_names.permissions').'.id', $permission->id);
                        }
                    });
                });
                if (count($rolesWithPermissions) > 0) {
                    $query->orWhereHas('roles', function ($query) use ($rolesWithPermissions) {
                        $query->where(function ($query) use ($rolesWithPermissions) {
                            foreach ($rolesWithPermissions as $role) {
                                $query->orWhere(config('permission.table_names.roles').'.id', $role->id);
                            }
                        });
                    });
                }
            });
    }

    /**
     * Assign the given role to the model.
     * If a restrictable instance is given, given roles(s) is/are scoped to it,
     *  otherwise there won't be a scope for the role(s).
     *
     * @param array|string|\Spatie\Permission\Contracts\Role $roles
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return $this
     */
    public function assignRole($roles, Restrictable $restrictable = null)
    {
        // Role objects, if directly collected, becomes arrays of fields and the flatten() messes with
        // the map function giving every single Role field as parameter for getStoredRole.
        // To avoid this, if a Role is given an empty collection is created and the role is pushed inside.
        // In this way, in case of a Role instance, the object is not flattened,
        // but for arrays, collections and string everything works as expected.
        $roles = (($roles instanceof Role) ? collect()->push($roles) : collect($roles))
            ->flatten()
            ->map(function ($role) {
                return $this->getStoredRole($role);
            })
            ->each(function ($role) {
                $this->ensureModelSharesGuard($role);
            })
            // Attach takes ids, we retrieve them
            ->map(function ($role) {
                return $role->id;
            })
            ->all();

        // If there is no restrictable instance, we won't add anything on the pivot table,
        //  which will default to null values on the restrictable morph.
        // Otherwise we set the references to it
        $this->roles()->attach($roles, is_null($restrictable) ? [] : [
            'restrictable_id' => $restrictable->getRestrictableId(),
            'restrictable_type' => $restrictable->getRestrictableTable(),
        ]);

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Revoke the given role from the model.
     * If a Restrictable instance is given, the role will be removed only for that instance scope.
     *
     * @param string|\Spatie\Permission\Contracts\Role $role
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     */
    public function removeRole($role, Restrictable $restrictable = null)
    {
        $this->roles($restrictable)->detach($this->getStoredRole($role));
    }

    /**
     * Remove all current roles and set the given ones.
     * If a Restrictable instance is given, roles will be removed and set only for that instance scope.
     *
     * @param array|string|\Spatie\Permission\Contracts\Role $roles
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return $this
     */
    public function syncRoles($roles, Restrictable $restrictable = null)
    {
        $this->roles($restrictable)->detach();

        return $this->assignRole($roles, $restrictable);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return bool
     */
    public function hasRole($roles, Restrictable $restrictable = null): bool
    {
        // Needed to preserve the caching mechanism at least for the not scoped roles
        $roleCachedRelation = (is_null($restrictable) ? $this->roles : $this->roles($restrictable)->get());

        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $roleCachedRelation->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $roleCachedRelation->contains('id', $roles->id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $restrictable)) {
                    return true;
                }
            }

            return false;
        }

        return $roles->intersect($roleCachedRelation)->isNotEmpty();
    }

    /**
     * Determine if the model has any of the given role(s).
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param array|string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return bool
     */
    public function hasAnyRole($roles, Restrictable $restrictable = null): bool
    {
        return $this->hasRole($roles, $restrictable);
    }

    /**
     * Determine if the model has all of the given role(s).
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param array|string|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return bool
     */
    public function hasAllRoles($roles, Restrictable $restrictable = null): bool
    {
        // Needed to preserve the caching mechanism at least for the not scoped roles
        $roleCachedRelation = (is_null($restrictable) ? $this->roles : $this->roles($restrictable)->get());

        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $roleCachedRelation->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $roleCachedRelation->contains('id', $roles->id);
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $roles->intersect($roleCachedRelation->pluck('name')) == $roles;
    }

    /**
     * Determine if the model may perform the given permission.
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param string|\Spatie\Permission\Contracts\Permission $permission
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @param string|null $guardName
     *
     * @return bool
     */
    public function hasPermissionTo($permission, Restrictable $restrictable = null, $guardName = null): bool
    {
        if (is_string($permission)) {
            $permission = app(Permission::class)->findByName(
                $permission,
                $guardName ?? $this->getDefaultGuardName()
            );
        }

        return $this->hasDirectPermission($permission, $restrictable) ||
            $this->hasPermissionViaRole($permission, $restrictable);
    }

    /**
     * Determine if the model has any of the given permissions.
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param array $permissions
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return bool
     */
    public function hasAnyPermission($permissions, Restrictable $restrictable = null): bool
    {
        if (is_array($permissions[0])) {
            $permissions = $permissions[0];
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission, $restrictable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model has, via roles, the given permission.
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param \Spatie\Permission\Contracts\Permission $permission
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return bool
     */
    protected function hasPermissionViaRole(Permission $permission, Restrictable $restrictable = null): bool
    {
        return $this->hasRole($permission->roles, $restrictable);
    }

    /**
     * Determine if the model has the given permission.
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param string|\Spatie\Permission\Contracts\Permission $permission
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return bool
     */
    public function hasDirectPermission($permission, Restrictable $restrictable = null): bool
    {
        if (is_string($permission)) {
            $permission = app(Permission::class)->findByName($permission, $this->getDefaultGuardName());

            if (!$permission) {
                return false;
            }
        }

        // Needed to preserve the caching mechanism at least for the not scoped permissions
        return (is_null($restrictable) ? $this->permissions : $this->permissions($restrictable)->get())
            ->contains('id', $permission->id);
    }

    /**
     * Return all permissions the directory coupled to the model.
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return \Illuminate\Support\Collection
     */
    public function getDirectPermissions(Restrictable $restrictable = null): Collection
    {
        // Needed to preserve the caching mechanism at least for the not scoped permissions
        return (is_null($restrictable) ? $this->permissions : $this->permissions($restrictable)->get());
    }

    /**
     * Return all the permissions the model has via roles.
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return \Illuminate\Support\Collection
     */
    public function getPermissionsViaRoles(Restrictable $restrictable = null): Collection
    {
        $this->load('roles', 'roles.permissions');


        return (is_null($restrictable) ? $this->roles : $this->roles($restrictable)->get())
            ->flatMap(function ($role) {
                return $role->permissions;
            })->sort()->values();
    }

    /**
     * Return all the permissions the model has, both directly and via roles.
     * If a Restrictable instance is given, the check will be performed only for that instance scope.
     *
     * @param \Spatie\Permission\Contracts\Restrictable $restrictable
     * @return \Illuminate\Support\Collection
     */
    public function getAllPermissions(Restrictable $restrictable = null): Collection
    {
        return $this->getDirectPermissions($restrictable)
            ->merge($this->getPermissionsViaRoles($restrictable))
            ->sort()
            ->values();
    }


    /**
     * @param string|\Spatie\Permission\Contracts\Role $role
     * @return Role
     */
    protected function getStoredRole($role): Role
    {
        if (is_numeric($role)) {
            return app(Role::class)->findById($role, $this->getDefaultGuardName());
        }

        if (is_string($role)) {
            return app(Role::class)->findByName($role, $this->getDefaultGuardName());
        }

        return $role;
    }

    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (! in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }
}
