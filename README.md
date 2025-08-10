# Laravel Type Generator

A Laravel package that automatically generates TypeScript types and OpenAPI specifications from your Laravel routes.

## Features

- Generates TypeScript types from Laravel routes
- Creates OpenAPI specifications for API documentation
- Provides a Swagger UI to visualize and interact with your API
- Supports Inertia.js type generation
- Analyzes route controllers, methods, and docblocks to generate accurate types
- Handles pagination, collections, and complex return types

## Installation

You can install the package via composer:

```bash
composer require martinpham/laravel-type-generator
```

### Publish Configuration

Publish the configuration file, views, and routes:

```bash
php artisan vendor:publish --tag="type-generator"
```

## Configuration

After publishing the configuration, you can find it at `config/type-generator.php`. Here you can configure:

- Ignored HTTP methods
- Route prefixes to include in the generation
- Output paths for generated files
- OpenAPI specification options

Example configuration:

```php
return [
    // HTTP methods to ignore when generating types
    'ignored_methods' => [
        'head',
        'options',
    ],
    
    // Routes to include in the generation
    'route_prefixes' => [
        'uri:api' => [
            'output' => resource_path('api/openapi.json'),
            'class' => 'MartinPham\TypeGenerator\Writers\OpenAPI\OpenAPI',
            'options' => [
                'openapi' => '3.0.2',
                'title' => 'My API',
                'version' => '1.0.0'
            ]
        ],
        'controller:App\Http\Controllers\Web' => [
            'output' => resource_path('js/types/inertia.d.ts'),
            'class' => 'MartinPham\TypeGenerator\Writers\Inertia\Inertia',
            'options' => []
        ]
    ],
    
    // Routes to exclude from generation
    'ignored_route_names' => [
        'api.openapi.',
        'api.not_found',
    ],
    
    // Return types to ignore
    'ignored_route_returns' => [
        'Illuminate\Http\RedirectResponse',
    ],
];
```

## Usage

### Generating Types

Run the following command to generate types:

```bash
php artisan type:generate
```

This will:
1. Analyze your Laravel routes
2. Extract type information from controllers and methods
3. Generate TypeScript types and/or OpenAPI specifications
4. Save the output to the configured locations

### Viewing API Documentation

After generating the OpenAPI specification, you can access the Swagger UI at:

```
/api/openapi
```

The JSON specification is available at:

```
/api/openapi.json
```

### Using Generated Types in TypeScript

For Inertia.js projects, you can import the generated types in your TypeScript files:

```typescript
import { user_list } from '@/types/inertia';

export default function Page({
    users
}: PageProps<user_list>) {

}

```

## How It Works

The package analyzes your Laravel routes and their corresponding controllers. It extracts type information from:

- Method return types
- PHPDoc annotations
- Parameter types
- Exception types

It then generates TypeScript types or OpenAPI specifications based on this information.

## Examples

### PHPDoc

```php
/**
 * Get a list of users
 * 
 * @return array{
 *  users: User[]
 * }
 */
public function list(): array
{
    return [
        'users' => $users
    ]
}
```
### PHPNative

```php
/**
 * Get user details
 */
public function detail(): User
{
    return $user;
}
```


## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
