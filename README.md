**TLDR:** If you've ever wanted fully type-safe integration between your Laravel backend and your frontend (whether
that's API consumers or Inertia.js apps), this package might save you a ton of time.

## What It Does

The `laravel-type-generator` package scans your Laravel routes, controllers, models, DTOs, and their properties,
methods, docblocks, and PHP source code. It then automatically generates:

- TypeScript types from your Laravel routes
- OpenAPI specifications for API documentation
- Swagger UI to visualize and interact with your OpenAPI spec
- Inertia.js type generation
- Accurate handling of pagination, collections, and complex return types

*(More transformers are coming soon!)*

Think of it as a bridge that keeps your Laravel backend and frontend perfectly synchronized with types.

## Installation

```bash
composer require martinpham/laravel-type-generator
```

Publish the config:

```bash
php artisan vendor:publish --tag="type-generator"
```

Then run:

```bash
php artisan types:generate
```

## Configuration

The package is flexible. In the config file, you can customize what gets generated and where it goes. Here's a practical
example showing how to generate:

- An OpenAPI specification JSON file for routes matching `/_api/*`
- TypeScript types for Inertia.js routes and template variables, for controllers matching `App\Http\Controllers\Web\*`

```php
'route_prefixes' => [
    'uri:_api' => [
        'output' => resource_path('api/openapi.json'),
        'class' => 'MartinPham\TypeGenerator\Writers\OpenAPI\OpenAPI',
        'options' => [
            'openapi' => '3.0.2',
            'title' => 'OpenAPI',
            'version' => '1.0.0'
        ]
    ],
    'controller:App\Http\Controllers\Web\\' => [
        'output' => resource_path('js/types/inertia-routes.ts'),
        'class' => 'MartinPham\TypeGenerator\Writers\Inertia\Inertia',
    ],
],
```

## Example: How Your Controller Becomes Typed API Specs

Let's say you have a simple controller method like this:

```php
class PlaygroundApiController extends Controller
{
    public function findUser(User $user): User
    {
        return $user;
    }
}
```

### Here's what happens under the hood:

1. The package analyzes the `findUser` method's input parameter (`User $user`) and return type (`User`).
2. It automatically generates an OpenAPI operation for this route with `$user` as a parameter.
3. It creates a `User` schema for both request and response types.
4. Since your User model has relationships (like Address and Country), it recursively generates schemas for those
   related models too — so your OpenAPI spec and TypeScript types reflect the complete data structure.

This means your frontend gets fully typed models, including nested relationships, without any extra manual work!

![OpenAPI](https://dev-to-uploads.s3.amazonaws.com/uploads/articles/iee1gqc0ytpk1f3x5gtx.png)

## Seamless Integration with Orval for Type-Safe API Clients & Inertia template

Once `laravel-type-generator` creates your OpenAPI spec, you can feed it directly into Orval to automatically generate
TypeScript types and API clients.

Here's what Orval would produce from the generated spec:

```typescript
export interface User {
  id?: number;
  name?: string;
  email?: string;
  role?: string;
  address?: Address;
}

export interface Address {
  id?: number;
  street?: string;
  country?: Country;
}

export interface Country {
  id?: number;
  name?: string;
  code?: string;
}

export const apiPlaygroundUsers = (
  user: string, 
  options?: AxiosRequestConfig
): Promise<AxiosResponse<User>> => {
  return axios.get(`/_api/playground/findUser/${user}`, options);
};
```

### What this means for you:

- Your frontend knows exactly what data to expect — including nested objects like Address and Country
- Your API calls are fully typed, with correct parameter and response types
- No more guesswork or brittle `any` types when consuming your Laravel backend APIs

This creates a tight feedback loop between your backend and frontend, boosting developer confidence and reducing bugs
from mismatched data structures.

### Inertia template

On Inertia side, you will receive exactly the types which were returned by the route

![Inertia](https://dev-to-uploads.s3.amazonaws.com/uploads/articles/6vyfsggfg1zt70oz2oae.png)

## Full Support for Various Data Types

The `laravel-type-generator` isn't limited to just Eloquent models. It also supports:

- Plain PHP classes
- Laravel Data objects (like from Spatie's laravel-data package)
- API Resources
- Your own custom-defined docblocks

To give you more control over the generated OpenAPI spec, you can use special docblock annotations in your controller
methods:

- `@id` — Overrides the default operationId (otherwise it's derived from the route name)
- `@tag` — Adds tags to group and organize your API operations
- `@throws` — Specify errors that could be thrown during the call
- Method descriptions are also captured in the OpenAPI document

This flexibility lets you define complex return types with complete accuracy. For example:

```php
/**
 * @return Collection<int, User>
 */
public function allUsers(User $user): Collection
{
    return User::all();
}

/**
 *
 * @return array{
 *     requestedAt: DateTime | null,
 *     users: User[],
 *     cars: Collection<int, Car>,
 *     carsWithPagination: LengthAwarePaginator<int, Car>,
 *     nestedObject: array{
 *         evenDeeper: array{
 *             message: string
 *         }
 *     }
 * }
 */
public function manything(Address $address): array
{
    return [];
}
```

### Here's what's happening:

The package reads the docblocks and uses PHP's native type hints to generate detailed, nested TypeScript types and
OpenAPI schemas.

Collections, paginated results, arrays with nested objects — everything gets parsed and converted correctly.

This means you can describe even very complex API responses in your controller methods and get fully typed clients on
the frontend without any manual synchronization.

## Specifying Request Parameters

Your API request parameters can come from different places, and `laravel-type-generator` supports all of them to
generate accurate types:

### 1. Route Path Parameters

Simply define parameters in your route URL, like `/users/{user}/`. These will be automatically detected and typed.

### 2. FormRequest Subclasses

You can define a FormRequest class with typed properties and validation rules. The package inspects both your docblock
properties and validation rules to generate the parameter types.

Example:

```php
/**
 * @property UploadedFile $file
 * @property string $nickname
 * @property array{
 *     name: string,
 *     age: int
 * } $person
 */
class GreetingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['file'],
        ];
    }
}
```

### 3. Docblock Annotations on Controller Methods

You can also specify request shapes directly in your controller method's docblocks using generics and param tags:

```php
/**
 * Greet
 *
 * Generates a greeting message.
 *
 * @id greeting
 * @param Request<array{
 *     user_id: int
 * }> $request
 * @param string $name The receiver's nickname.
 * @return Message|null The greeting message
 * @throws InvalidName Invalid name provided
 * @throws \InvalidArgumentException Invalid args provided
 */
public function greeting(Request $request, $name): ?Message
{
    // ...
}
```

This tells the package exactly what your request parameters should look like, allowing for precise type generation.

