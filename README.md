# Associate users with permissions and roles

* [Installation](#installation)
* [Usage](#usage)
  * [Using "direct" permissions](#using-direct-permissions-see-below-to-use-both-roles-and-permissions)
  * [Using permissions via roles](#using-permissions-via-roles)
  * [Using Blade directives](#using-blade-directives)
  * [Using multiple guards](#using-multiple-guards)
  * [Using a middleware](#using-a-middleware)
  * [Using artisan commands](#using-artisan-commands)
* [Unit Testing](#unit-testing)
* [Database Seeding](#database-seeding)
* [Extending](#extending)
* [Cache](#cache)

This package allows you to manage user permissions and roles in a database.
It's based on [Spatie](https://github.com/spatie/laravel-permission) package and that is why the namespace has his name and I kept all the referenced to their site: I just modded the package, they are the one to thank for the initial work.

Once installed you can do stuff like this:

```php
// Adding permissions to a user
$user->givePermissionTo('edit-articles');

// Adding permissions via a role
$user->assignRole('writer');

$role->givePermissionTo('edit-articles');
```

If you're using multiple guards we've got you covered as well. Every guard will have its own set of permissions and roles that can be assigned to the guard's users. Read about it in the [using multiple guards](#using-multiple-guards) section of the readme.

Because all permissions will be registered on [Laravel's gate](https://laravel.com/docs/5.5/authorization), you can test if a user has a permission with Laravel's default `can` function:

```php
$user->can('edit-articles');
```

Spatie is a web design agency in Antwerp, Belgium. You'll find an overview of all 
their open source projects [on our website](https://spatie.be/opensource).I work at Dreamonkey Srl, a startup in Scandiano, Italy, as backend and Android developer. You can find a description of who we are [on our website](https://www.dreamonkey.com/) (still no english translation, though).

## Installation

- [Laravel](#laravel)
- [Lumen](#lumen)

### Laravel

This package can be used in Laravel 5.4 or higher. If you are using an older version of Laravel, take a look at [the v1 branch of this package](https://github.com/spatie/laravel-permission/tree/v1).

You can install the package via composer.
Being a modded version, it's not present on packagist for now; for this reason you have to manually add the repository to the composer.json file and then add the require line.

``` bash
    ...
    "repositories": [
        ...
        {
            "type": "vcs",
            "url": "https://github.com/IlCallo/laravel-permission"
        }
    ],
    "require": {
        ...
        "spatie/laravel-permission": "^2.2.0"
        ...
    }
```

In Laravel 5.5 the service provider will automatically get registered. In older versions of the framework just add the service provider in `config/app.php` file:

```php
'providers' => [
    // ...
    Spatie\Permission\PermissionServiceProvider::class,
];
```

You can publish [the migration](https://github.com/spatie/laravel-permission/blob/master/database/migrations/create_permission_tables.php.stub) with:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
```

After the migration has been published you can create the role- and permission-tables by running the migrations:

```bash
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"
```

When published, [the `config/permission.php` config file](https://github.com/spatie/laravel-permission/blob/master/config/permission.php) contains:

```php
return [

    'models' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Spatie\Permission\Contracts\Permission` contract.
         */

        'permission' => Spatie\Permission\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Spatie\Permission\Contracts\Role` contract.
         */

        'role' => Spatie\Permission\Models\Role::class,

    ],

    'table_names' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'roles' => 'roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your permissions. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'permissions' => 'permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_permissions' => 'model_has_permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models roles. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_roles' => 'model_has_roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'role_has_permissions' => 'role_has_permissions',
    ],

    /*
     * By default all permissions will be cached for 24 hours unless a permission or
     * role is updated. Then the cache will be flushed immediately.
     */

    'cache_expiration_time' => 60 * 24,
];
```

### Lumen

You can install the package via Composer:

``` bash
composer require spatie/laravel-permission
```

Copy `vendor/spatie/laravel-permission/config/permission.php` to `config/permission.php`

Copy `vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub` to `database/migrations/2017_12_01_010101_create_permission_tables.php`

In `bootstrap/app.php`, add the following code below other service providers:

```php
$app->register(Spatie\Permission\PermissionServiceProvider::class);
$app->configure('permission');
```

Then, run your migrations:

```bash
php artisan migrate
```

## Usage

First, add the `Spatie\Permission\Traits\HasRoles` trait to your `User` model(s):

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // ...
}
```

> - note that if you need to use `HasRoles` trait with another model ex.`Page` you will also need to add `protected $guard_name = 'web';` as well to that model or you would get an error
>
>```php
>use Illuminate\Database\Eloquent\Model;
>use Spatie\Permission\Traits\HasRoles;
>
>class Page extends Model
>{
>    use HasRoles;
>
>    protected $guard_name = 'web'; // or whatever guard you want to use
>
>    // ...
>}
>```

This package allows for users to be associated with permissions and roles. Every role is associated with multiple permissions.
A `Role` and a `Permission` are regular Eloquent models. They require a `name` and can be created like this:

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$role = Role::create(['name' => 'writer']);
$permission = Permission::create(['name' => 'edit-articles']);
```

If you're using multiple guards the `guard_name` attribute needs to be set as well. Read about it in the [using multiple guards](#using-multiple-guards) section of the readme.

The `HasRoles` trait adds Eloquent relationships to your models, which can be accessed directly or used as a base query:

```php
// get a list of all permissions directly assigned to the user
$permissions = $user->permissions;

// get all permissions inherited by the user via roles
$permissions = $user->getAllPermissions();

// get a collection of all defined roles
$roles = $user->getRoleNames(); // Returns a collection
```

The `HasRoles` trait also adds a `role` scope to your models to scope the query to certain roles or permissions:

```php
$users = User::role('writer')->get(); // Returns only users with the role 'writer'
```

The `role` scope can accept a string, a `\Spatie\Permission\Models\Role` object or an `\Illuminate\Support\Collection` object.

The same trait also adds a scope to only get users that have a certain permission.

```php
$users = User::permission('edit articles')->get(); // Returns only users with the permission 'edit articles' (inherited or directly)
```

The scope can accept a string, a `\Spatie\Permission\Models\Permission` object or an `\Illuminate\Support\Collection` object.

### Using "direct" permissions (see below to use both roles and permissions)

A permission can be given to any user:

```php
$user->givePermissionTo('edit-articles');

// You can also give multiple permission at once using an array
$user->givePermissionTo(['edit-articles', 'delete-articles']);
```

A permission can be revoked from a user:

```php
$user->revokePermissionTo('edit-articles');
```

Or revoke & add new permissions in one go:

```php
$user->syncPermissions(['edit articles', 'delete articles']);
```

You can test if a user has a permission:

```php
$user->hasPermissionTo('edit-articles');
```

...or if a user has multiple permissions:

```php
$user->hasAnyPermission(['edit articles', 'publish articles', 'unpublish articles']);
```

Saved permissions will be registered with the `Illuminate\Auth\Access\Gate` class for the default guard. So you can
test if a user has a permission with Laravel's default `can` function:

```php
$user->can('edit-articles');
```

### Using permissions via roles

A role can be assigned to any user:

```php
$user->assignRole('writer');

// You can also assign multiple roles at once
$user->assignRole('writer', 'admin');
// or as an array
$user->assignRole(['writer', 'admin']);
```

A role can be removed from a user:

```php
$user->removeRole('writer');
```

Roles can also be synced:

```php
// All current roles will be removed from the user and replaced by the array given
$user->syncRoles(['writer', 'admin']);
```

You can determine if a user has a certain role:

```php
$user->hasRole('writer');
```

You can also determine if a user has any of a given list of roles:

```php
$user->hasAnyRole(Role::all());
```

You can also determine if a user has all of a given list of roles:

```php
$user->hasAllRoles(Role::all());
```

The `assignRole`, `hasRole`, `hasAnyRole`, `hasAllRoles`  and `removeRole` functions can accept a
 string, a `\Spatie\Permission\Models\Role` object or an `\Illuminate\Support\Collection` object.

A permission can be given to a role:

```php
$role->givePermissionTo('edit-articles');
```

You can determine if a role has a certain permission:

```php
$role->hasPermissionTo('edit-articles');
```

A permission can be revoked from a role:

```php
$role->revokePermissionTo('edit-articles');
```

The `givePermissionTo` and `revokePermissionTo` functions can accept a
string or a `Spatie\Permission\Models\Permission` object.


Permissions are inherited from roles automatically. 
Additionally, individual permissions can be assigned to the user too. 
For instance:

```php
$role = Role::findByName('writer');
$role->givePermissionTo('edit-articles');

$user->assignRole('writer');

$user->givePermissionTo('delete-articles');
```

In the above example, a role is given permission to edit articles and this role is assigned to a user. 
Now the user can edit articles and additionally delete articles. The permission of 'delete articles' is the user's direct permission because it is assigned directly to them.
When we call `$user->hasDirectPermission('delete-articles')` it returns `true`, 
but `false` for `$user->hasDirectPermission('edit-articles')`.

This method is useful if one builds a form for setting permissions for roles and users in an application and wants to restrict or change inherited permissions of roles of the user, i.e. allowing to change only direct permissions of the user.

You can list all of these permissions:

```php
// Direct permissions
$user->getDirectPermissions() // Or $user->permissions;

// Permissions inherited from the user's roles
$user->getPermissionsViaRoles();

// All permissions which apply on the user (inherited and direct)
$user->getAllPermissions();
```

All these responses are collections of `Spatie\Permission\Models\Permission` objects.

If we follow the previous example, the first response will be a collection with the `delete article` permission and 
the second will be a collection with the `edit article` permission and the third will contain both.

### Using permissions and roles with scopes

If a scope is not explicitly specified, all methods will operate on a not scoped level, which means that a user's roles
and permissions are valid per user.

To grant permissions to a user related to a resource (eg. ability to edit all articles produced by a single department)
it's possible to "scope" the permissions and roles to a particular model, which must implement the `Restrictable` 
interface. A default implementation based on the Eloquent `Model` class can be added to your model(s) with 
the `RestrictableTrait`.

To scope permissions and roles, a `Restrictable` instance can be provided as second parameter to all the methods
which grant, revoke, sync or get permissions or roles.

```php
$role->givePermissionTo('edit-articles');

$user->assignRole('writer', $department);

$user->givePermissionTo('delete-articles', $department);

$user->syncRoles(['writer', 'admin'], $department);

// Scoped direct permissions
$user->getDirectPermissions($department)

// Permissions inherited from user's scoped roles
$user->getPermissionsViaRoles($department);

// All permissions which apply on the user for the given department
$user->getAllPermissions($department);

$user->can('edit-articles', $departement);
```

Do note that Roles and Permissions can be scoped, but scoped permissions assigned to a role are not currently supported.

### Using Blade directives
This package also adds Blade directives to verify whether the currently logged in user has all or any of a given list of roles. 

Optionally you can pass in the `guard` that the check will be performed on as a second argument.

#### Blade and Roles
Test for a specific role:
```php
@role('writer')
    I am a writer!
@else
    I am not a writer...
@endrole
```
is the same as
```php
@hasrole('writer')
    I am a writer!
@else
    I am not a writer...
@endhasrole
```

Test for any role in a list:
```php
@hasanyrole($collectionOfRoles)
    I have one or more of these roles!
@else
    I have none of these roles...
@endhasanyrole
// or
@hasanyrole('writer|admin')
    I am either a writer or an admin or both!
@else
    I have none of these roles...
@endhasanyrole
```
Test for all roles:

```php
@hasallroles($collectionOfRoles)
    I have all of these roles!
@else
    I do not have all of these roles...
@endhasallroles
// or
@hasallroles('writer|admin')
    I am both a writer and an admin!
@else
    I do not have all of these roles...
@endhasallroles
```

#### Blade and Permissions
This package doesn't add any permission-specific Blade directives. Instead, use Laravel's native `@can` directive to check if a user has a certain permission.

```php
@can('edit articles')
  //
@endcan
```
or
```php
@if(auth()->user()->can('edit articles') && $some_other_condition)
  //
@endif
```

## Using multiple guards

When using the default Laravel auth configuration all of the above methods will work out of the box, no extra configuration required.

However, when using multiple guards they will act like namespaces for your permissions and roles. Meaning every guard has its own set of permissions and roles that can be assigned to their user model.

### Using permissions and roles with multiple guards

By default the default guard (`config('auth.defaults.guard')`) will be used as the guard for new permissions and roles. When creating permissions and roles for specific guards you'll have to specify their `guard_name` on the model:

```php
// Create a superadmin role for the admin users
$role = Role::create(['guard_name' => 'admin', 'name' => 'superadmin']);

// Define a `publish articles` permission for the admin users belonging to the admin guard
$permission = Permission::create(['guard_name' => 'admin', 'name' => 'publish articles']);

// Define a *different* `publish articles` permission for the regular users belonging to the web guard
$permission = Permission::create(['guard_name' => 'web', 'name' => 'publish articles']);
```

To check if a user has permission for a specific guard:

```php
$user->hasPermissionTo('publish articles', 'admin');
```

### Assigning permissions and roles to guard users

You can use the same methods to assign permissions and roles to users as described above in [using permissions via roles](#using-permissions-via-roles). Just make sure the `guard_name` on the permission or role matches the guard of the user, otherwise a `GuardDoesNotMatch` exception will be thrown.

### Using blade directives with multiple guards

You can use all of the blade directives listed in [using blade directives](#using-blade-directives) by passing in the guard you wish to use as the second argument to the directive:

```php
@role('super-admin', 'admin')
    I am a super-admin!
@else
    I am not a super-admin...
@endrole
```

### Using blade directives with scoped roles

To use Blade directives with scoped roles, parameters to provide are `role_name, guard_name, restrictable_fully_qualified_class, restrictable_id`.
Most of the time, you want to provide a null `guard_name`, while `restrictable_fully_qualified_class` and 
`restrictable_id` must both be not null, or they will be ignored.

## Using a middleware

This package comes with `RoleMiddleware` and `PermissionMiddleware` middleware. You can add them inside your `app/Http/Kernel.php` file.

```php
protected $routeMiddleware = [
    // ...
    'role' => \Spatie\Permission\Middlewares\RoleMiddleware::class,
    'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
];
```

Then you can protect your routes using middleware rules:

```php
Route::group(['middleware' => ['role:super-admin']], function () {
    //
});

Route::group(['middleware' => ['permission:publish articles']], function () {
    //
});

Route::group(['middleware' => ['role:super-admin','permission:publish articles']], function () {
    //
});
```

Alternatively, you can separate multiple roles or permission with a `|` (pipe) character:

```php
Route::group(['middleware' => ['role:super-admin|writer']], function () {
    //
});

Route::group(['middleware' => ['permission:publish articles|edit articles']], function () {
    //
});
```

You can protect your controllers similarly, by setting desired middleware in the constructor:

```php
public function __construct()
{
    $this->middleware(['role:super-admin','permission:publish articles|edit articles']);
}
```

### Catching role and permission failures
If you want to override the default `403` response, you can catch the `UnauthorizedException` using your app's exception handler:

```php
public function render($request, Exception $exception)
{
    if ($exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
        // Code here ...
    }

    return parent::render($request, $exception);
}

```

## Using artisan commands

You can create a role or permission from a console with artisan commands.

```bash
php artisan permission:create-role writer
```

```bash
php artisan permission:create-permission 'edit articles'
```

When creating permissions and roles for specific guards you can specify the guard names as a second argument:

```bash
php artisan permission:create-role writer web
```

```bash
php artisan permission:create-permission 'edit articles' web
```

## Unit Testing

In your application's tests, if you are not seeding roles and permissions as part of your test `setUp()` then you may run into a chicken/egg situation where roles and permissions aren't registered with the gate (because your tests create them after that gate registration is done). Working around this is simple: In your tests simply add a `setUp()` instruction to re-register the permissions, like this:

```php
    public function setUp()
    {
        // first include all the normal setUp operations
        parent::setUp();

        // now re-register all the roles and permissions
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->registerPermissions();
    }
```

## Database Seeding

Two notes about Database Seeding:

1. It is best to flush the `spatie.permission.cache` before seeding, to avoid cache conflict errors. This can be done from an Artisan command (see Troubleshooting: Cache section, later) or directly in a seeder class (see example below).

2. Here's a sample seeder, which clears the cache, creates permissions and then assigns permissions to roles:

	```php
	use Illuminate\Database\Seeder;
	use Spatie\Permission\Models\Role;
	use Spatie\Permission\Models\Permission;

	class RolesAndPermissionsSeeder extends Seeder
	{
	    public function run()
    	{
        	// Reset cached roles and permissions
	        app()['cache']->forget('spatie.permission.cache');

	        // create permissions
	        Permission::create(['name' => 'edit articles']);
	        Permission::create(['name' => 'delete articles']);
	        Permission::create(['name' => 'publish articles']);
	        Permission::create(['name' => 'unpublish articles']);

	        // create roles and assign existing permissions
	        $role = Role::create(['name' => 'writer']);
	        $role->givePermissionTo('edit articles');
	        $role->givePermissionTo('delete articles');

	        $role = Role::create(['name' => 'admin']);
	        $role->givePermissionTo('publish articles');
	        $role->givePermissionTo('unpublish articles');
	    }
	}

	```

## Extending

If you need to extend or replace the existing `Role` or `Permission` models you just need to
keep the following things in mind:

- Your `Role` model needs to implement the `Spatie\Permission\Contracts\Role` contract
- Your `Permission` model needs to implement the `Spatie\Permission\Contracts\Permission` contract
- You can publish the configuration with this command:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"
```
  
  And update the `models.role` and `models.permission` values

### Cache

If you manipulate unscoped permission/role data directly in the database instead of calling the supplied methods, then you will not see the changes reflected in the application, because unscoped role and permission data is cached to speed up performance.

When you use the supplied methods for manipulating roles and permissions, the cache is automatically reset for you:

```php
$user->assignRole('writer');
$user->removeRole('writer');
$user->syncRoles(params);
$role->givePermissionTo('edit-articles');
$role->revokePermissionTo('edit-articles');
$role->syncPermissions(params);
```

HOWEVER, if you manipulate permission/role data directly in the database instead of calling the supplied methods, then you will not see the changes reflected in the application unless you manually reset the cache.

### Manual cache reset
To manually reset the cache for this package, run:
```bash
php artisan cache:forget spatie.permission.cache
```

### Cache Identifier

TIP: If you are leveraging a caching service such as `redis` or `memcached` and there are other sites 
running on your server, you could run into cache clashes. It is prudent to set your own cache `prefix` 
in `/config/cache.php` to something unique for each application. This will prevent other applications 
from accidentally using/changing your cached data.


## Need a UI?

The package doesn't come with any screens out of the box, you should build that yourself. To get started check out [this extensive tutorial](https://scotch.io/tutorials/user-authorization-in-laravel-54-with-spatie-laravel-permission) by [Caleb Oki](http://www.caleboki.com/).

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security-related issues, please email [freek@spatie.be](mailto:freek@spatie.be) instead of using the issue tracker.

## Postcardware

You're free to use this package (it's [MIT-licensed](LICENSE.md)), but if it makes it to your production environment Spatie highly appreciate you sending them a postcard from your hometown, mentioning which of their package(s) you are using.

Their address is: Spatie, Samberstraat 69D, 2060 Antwerp, Belgium.

All received postcards are published [on their website](https://spatie.be/en/opensource/postcards).

If you also wanna send a postcard to us, we appreciate it too.

Our address is: Dreamonkey Srl, Via Mazzacurati 3/B, Scandiano 42019, RE, Italy.

## Credits

- [Spatie](https://github.com/spatie)
- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

This package is a modded version of Spatie one, which is in turn heavily based on [Jeffrey Way](https://twitter.com/jeffrey_way)'s awesome [Laracasts](https://laracasts.com) lessons on [permissions and roles](https://laracasts.com/series/whats-new-in-laravel-5-1/episodes/16). His original code can be found [in this repo on GitHub](https://github.com/laracasts/laravel-5-roles-and-permissions-demo).

Special thanks to [Alex Vanderbist](https://github.com/AlexVanderbist) who greatly helped with `v2`, and to [Chris Brown](https://github.com/drbyte) for his longtime support  helping us maintain the package.

## Resources

- [How to create a UI for managing the permissions and roles](http://www.qcode.in/easy-roles-and-permissions-in-laravel-5-4/)

## Alternatives

- [JosephSilber/bouncer](https://github.com/JosephSilber/bouncer)
- [Zizaco/entrust](https://github.com/Zizaco/entrust)
- [bican/roles](https://github.com/romanbican/roles)
- [spatie/laravel-permission](https://github.com/spatie/laravel-permission)

## Support us

Spatie is a web design agency based in Antwerp, Belgium. You'll find an overview of all our open source projects [on our website](https://spatie.be/opensource).

Does your business depend on our contributions? Reach out and support us on [Patreon](https://www.patreon.com/spatie). 
All pledges will be dedicated to allocating workforce on maintenance and new awesome stuff.

## About me and Dreamonkey

I work at Dreamonkey Srl, a startup in Scandiano, Italy, as backend and Android developer. You can find a description of who we are [on our website](https://www.dreamonkey.com/) (still no english translation, though).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
