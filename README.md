[![Coverage Status](https://coveralls.io/repos/github/php-grape/grape-entity/badge.svg)](https://coveralls.io/github/php-grape/grape-entity)
[![php-grape](https://circleci.com/gh/php-grape/grape-entity.svg?style=svg)](https://circleci.com/gh/php-grape/grape-entity)


# Table of Contents

- [PhpGrape\Entity](#phpgrapeentity)
  - [Introduction](#introduction)
  - [Installation](#installation)
  - [Example](#example)
  - [Reusable Responses with Entities](#reusable-responses-with-entities)
  - [Defining Entities](#defining-entities)
    - [Basic Exposure](#basic-exposure)
    - [Exposing with a Presenter](#exposing-with-a-presenter)
    - [Conditional Exposure](#conditional-exposure)
    - [Safe Exposure](#safe-exposure)
    - [Nested Exposure](#nested-exposure)
    - [Collection Exposure](#collection-exposure)
    - [Merge Fields](#merge-fields)
    - [Runtime Exposure](#runtime-exposure)
    - [Unexpose](#unexpose)
    - [Overriding exposures](#overriding-exposures)
    - [Returning only the fields you want](#returning-only-the-fields-you-want)
    - [Aliases](#aliases)
    - [Format Before Exposing](#format-before-exposing)
    - [Expose null](#expose-null)
    - [Default Value](#default-value)
    - [Documentation](#documentation)
  - [Options](#options)
    - [Passing Additional Option To Nested Exposure](#passing-additional-option-to-nested-exposure)
    - [Attribute Path Tracking](#attribute-path-tracking)
  - [Using Entities](#using-entities)
  - [JSON and XML formats](#json-and-xml-formats)
  - [Key transformer](#key-transformer)
  - [Adapters](#adapters)
    - [Laravel / Eloquent adapter](#laravel--eloquent-adapter)
  - [Testing with Entities](#testing-with-entities)
  - [Contributing](#contributing)
  - [License](#license)


# PhpGrape\Entity

## Introduction

Heavily inspired by [ruby-grape/grape-entity](https://github.com/ruby-grape/grape-entity).

This package adds Entity support to API frameworks. PhpGrape's Entity is an API focused facade that sits on top of an object model.

While this can be achieved with Transformers (using Laravel), it provides a cleaner approach with more features.

## Installation

```
  composer require php-grape/grape-entity
```

### Example

```php
class StatusEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::formatWith('iso_timestamp', function($dt) {
      return $dt->format(DateTime::ISO8601);
    });

    self::expose('user_name');
    self::expose('text', [
      'documentation' => ['type' => 'String', 'desc' => 'Status update text'
    ]);
    self::expose('ip', ['if' => ['type' => 'full']]);
    self::expose('user_type', 'user_id', ['if' => function($status, $options) {
      return $status->user->isPublic();
    }]);
    self::expose('location', ['merge' => true]);
    self::expose('contact_info', function() {
      self::expose('phone');
      self::expose('address', ['merge' => true, 'using' => AddressEntity::class]);
    });
    self::expose('digest', function($status, $options) {
      return md5($status->text);
    });
    self::expose('replies', ['as' => 'responses', 'using' => StatusEntity::class]);
    self::expose('last_reply', ['using' => StatusEntity::class], function($status, $options) {
      return $status->replies->last;
    });

    self::withOptions(['format_with' => 'iso_timestamp'], function() {
      self::expose('created_at');
      self::expose('updated_at');
    });
  }
}

class StatusDetailedEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::extends(StatusEntity::class);

    self::expose('internal_id');
  }
}
```

## Reusable Responses with Entities

Entities are a reusable means for converting PhP objects to API responses. Entities can be used to conditionally include fields, nest other entities, and build ever larger responses, using inheritance.

### Defining Entities

Entities inherit from `PhpGrape\Entity` and use `PhpGrape\EntityTrait`. Exposures can use runtime options to determine which fields should be visible, these options are available to `'if'`, and `'func'`.

PhP doesn't support multiple inheritance, so if you need your entities to inherits from multiple class use the `extends` method

Example:
```php
class AEntity extends Entity
{
  use EntityTrait;

  // `initialized` will be called automatically when needed :)
  private static function initialize()
  {
    self::extends(BEntity::class, CEntity::class, DEntity::class);
  
    ...
  }
}
```

#### Basic Exposure

Define a list of fields that will always be exposed.

```php
self::expose('user_name', 'ip');
```

The field lookup takes several steps

* first try `entity-instance->exposure`
* next try `entity-instance->exposure()`
* next try `object->exposure` (magic `__get` method included)
* next try `object->exposure()` (magic `__call` method included)
* last raise a `MissingPropertyException`

**NOTE**: protected and private properties/methods are exposed by default. You can change this behavior by setting one or all of these static properties to true:

```php
use PhpGrape\Reflection;

Reflection::$disableProtectedProps = true;
Reflection::$disablePrivateProps = true;
Reflection::$disableProtectedMethods = true;
Reflection::$disablePrivateMethods = true;
```


```php
class StatusEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('code');
    self::expose('message');
  }
}

$representation = StatusEntity::represent(['code' => 418, 'message' => "I'm a teapot"]);
json_encode($representation); // => { code: 418, message: "I'm a teapot" }
```

#### Exposing with a Presenter

Don't derive your model classes from `Grape::Entity`, expose them using a presenter.

```php
self::expose('replies', ['as' => 'responses', 'using' => StatusEntity::class]);
```

#### Conditional Exposure

Use `'if'` to expose fields conditionally.

```php
self::expose('ip', ['if' => ['type' => 'full']]);

// Exposed if the function evaluates to true
self::expose('ip', ['if' => function($instance, $options) {
  return isset($options['type']) && $options['type'] === 'full';
}]);

// Exposed if 'type' is set in the options array and is truthy
self::expose('ip', ['if' => 'type']);

// Exposed if $options['type'] is set and equals 'full'
self::expose('ip', ['if' => ['type' => 'full']]);
```

#### Safe Exposure

Don't raise an exception and expose as null, even if the field cannot be evaluated.

```php
self::('expose', 'ip', ['safe' => true]);
```

#### Nested Exposure

Supply a function to define an array using nested exposures.

```php
self::expose('contact_info', function() {
  self::expose('phone');
  self::expose('address', ['using' => Addressentity::class]);
});
```

You can also conditionally expose attributes in nested exposures:\
```php
self::expose('contact_info', function() {
  self::expose('phone')
  self::expose('address', ['using' => AddressEntity::class]);
  self::expose('email', ['if' => ['type' => 'full']);
});
```

#### Collection Exposure

Use `self::root(plural, singular = null)` to expose an object or a collection of objects with a root key.

```php
self::root('users', 'user');
self::expose('id', 'name');
```

By default every object of a collection is wrapped into an instance of your `Entity` class.
You can override this behavior and wrap the whole collection into one instance of your `Entity`
class.

As example:

```php
// `collection_name` is optional and defaults to `items`
self::presentCollection(true, 'collection_name');
self::expose('collection_name', ['using' => ItemEntity::class]);
```

#### Merge Fields

Use `'merge'` option to merge fields into the array or into the root:

```php
self::expose('contact_info', function() {
  self::expose('phone');
  self::expose('address', ['merge' => true, 'using' => AddressEntity::class]);
});

self::expose('status', ['merge' => true]);
```

This will return something like:

```php
{ contact_info: { phone: "88002000700", city: 'City 17', address_line: 'Block C' }, text: 'HL3', likes: 19 }
```

It also works with collections:

```php
self::expose('profiles', function() {
  self::expose('users', ['merge' => true, 'using' => UserEntity::class]);
  self::expose('admins', ['merge' => true, 'using' => AdminEntity::class]);
});
```

Provide closure to solve collisions:

```php
self::expose('status', ['merge' => function($key, $oldVal, $newVal) { 
  // You don't need to check if $oldVal is set here
  return $oldVal && $newVal ? $oldVal + $newVal : null;
}]);
```

#### Runtime Exposure

Use a closure to evaluate exposure at runtime. The supplied function
will be called with two parameters: the represented object and runtime options.

**NOTE:** A closure supplied with no parameters will be evaluated as a nested exposure (see above).

```php
self::expose('digest', function($status, $options) {
  return md5($status->txt);
});
```

You can also use the `'func'` option, which is similar.
Only difference is, this option will also accept a string (representing the name of a function), which can be convenient sometimes.
```php
// equivalent
function getDigest($status, $options) {
  return md5($status->txt);
}

...

self::expose('digest', ['func' => 'getDigest']);
```

You can also define a method or a property on the entity and it will try that before trying
on the object the entity wraps.

```php
class ExampleEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('attr_not_on_wrapped_object');
  }

  private function attr_not_on_wrapped_object()
  {
    return 42;
  }
}
```

You always have access to the presented instance (`object`) and the top-level
entity options (`options`).

```php
class ExampleEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('formatted_value');
  }
  
  private function formatted_value()
  {
    return "+ X {$this->object->value} {$this->options['y']}"
  }
}
```

#### Unexpose

To undefine an exposed field, use the ```unexpose``` method. Useful for modifying inherited entities.

```php
class UserEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('name');
    self::expose('address1');
    self::expose('address2');
    self::expose('address_state');
    self::expose('address_city');
    self::expose('email');
    self::expose('phone');
  }
}

class MailingEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::extends(UserEntity::class);
  
    self::unexpose('email');
    self::unexpose('phone');
  }
}
```

#### Overriding exposures

If you want to add one more exposure for the field but don't want the first one to be fired (for instance, when using inheritance), you can use the `override` flag. For instance:

```php
class UserEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('name');
  }
}

class EmployeeEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::extends(UserEntity::class);

    self::expose('name', ['as' => 'employe_name', 'override' => true]);
  }
}
```

`User` will return something like this `{ "name" : "John" }` while `Employee` will present the same data as `{ "employee_name" : "John" }` instead of `{ "name" : "John", "employee_name" : "John" }`.

#### Returning only the fields you want

After exposing the desired attributes, you can choose which one you need when representing some object or collection by using the only: and except: options. See the example:

```php
class UserEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('id');
    self::expose('name');
    self::expose('email');
  }
}

class ExampleEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('id');
    self::expose('title');
    self::expose('user', ['using' => UserEntity::class]);
  }
}

$data = ExampleEntity::represent($model, [
  'only' => ['title', ['user' => ['name', 'email']]]
]);
json_encode($data);
```

This will return something like this:

```php
{
  title: 'grape-entity is awesome!',
  user: {
  name: 'John Doe',
  email: 'john@example.com'
  }
}
```

Instead of returning all the exposed attributes.


The same result can be achieved with the following exposure:

```php
$data = ExampleEntity::represent($model, 
  'except' => ['id', ['user' => 'id']]
);
json_encode($data);
```

#### Aliases

Expose under a different name with `'as'`.

```php
self::expose('replies', ['as' => 'responses', 'using' => StatusEntity::class]);
```

#### Format Before Exposing

Apply a formatter before exposing a value.

```php
class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::formatWith('iso_timestamp', function($dt) {
      return $dt->format(DateTime::ISO8601);
    });

    self::withOptions(['format_with' => 'iso_timestamp'], function() {
      self::expose('created_at');
      self::expose('updated_at');
    });
  }
}
```

Defining a reusable formatter between multiples entities:

```php
Entity::formatWith('utc', function($dt) {
  return $dt->setTimezone(new DateTimeZone('UTC'));
});

class AnotherEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('created_at', ['format_with' => 'utc']);
  }
}
```

#### Expose Null

By default, exposures that contain `null` values will be represented in the resulting JSON.

As an example, an array with the following values:

```php
[
  'name' => null,
  'age' => 100
]
```

will result in a JSON object that looks like:

```javascript
{
  "name": null,
  "age": 100
}
```

There are also times when, rather than displaying an attribute with a `null` value, it is more desirable to not display the attribute at all. Using the array from above the desired JSON would look like:

```javascript
{
  "age": 100
}
```

In order to turn on this behavior for an as-exposure basis, the option `expose_null` can be used. By default, `expose_null` is considered to be `true`, meaning that `null` values will be represented in JSON. If `false` is provided, then attributes with `null` values will be omitted from the resulting JSON completely.

```php
class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('name', ['expose_null' => false]);
    self::expose('age', ['expose_null' => false]);
  }
}
```

`expose_null` is per exposure, so you can suppress exposures from resulting in `null` or express `null` values on a per exposure basis as you need:

```php
class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('name', ['expose_null' => false]);
    // since expose_null is omitted null values will be rendered
    self::expose('age');
  }
}
```

It is also possible to use `expose_null` with `withOptions` if you want to add the configuration to multiple exposures at once.

```php
class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    // None of the exposures in the withOptions closure will render null values
    self::withOptions(['expose_null' => false], function() {
      self::expose('name');
      self::expose('age');
    });
  }
}
```

When using `withOptions`, it is possible to again override which exposures will render `null` by adding the option on a specific exposure.

```php
class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    // None of the exposures in the withOptions closure will render null values
    self::withOptions(['expose_null' => false], function() {
      self::expose('name');
      // null values would be rendered in the JSON
      self::expose('age', ['expose_null' => true]);
    });
  }
}
```

#### Default Value

This option can be used to provide a default value in case the return value is null or false or empty (string or array).

```php
class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('name', ['default' => '']);
    self::expose('age', ['default' => 60]);
  }
}
```

#### Documentation

Expose documentation with the field. Gets bubbled up when used with various API documentation systems.

```php
self::expose('text', [
  'documentation' => ['type' => 'String', 'desc' => "Status update text."]
]);
```

### Options

The option key `'collection'` is always defined. The `'collection'` key is boolean, and defined as `true` if the object presented is iterable, `false` otherwise.

Any additional options defined on the entity exposure are included as is. In the following example `user` is set to the value of `current_user`.

```php
class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('user', ['if' => function($instance, $options) {
      return isset($options['user']);
    }], function($instance, $options) {
      return $options['user'];
    });
  }
}
```

```php
MyEntity::represent($s, ['using' => StatusEntity::class, 'user' => current_user()]);
```

#### Passing Additional Option To Nested Exposure
Sometimes you want to pass additional options or parameters to nested a exposure. For example, let's say that you need to expose an address for a contact info and it has two different formats: **full** and **simple**. You can pass an additional `full_format` option to specify which format to render.

```php
// api/ContactEntity.php
self::expose('contact_info', function() {
  self::expose('phone');
  self::expose('address', function($instance, $options) {
  // use `array_merge` to extend options and then pass the new version of options to the nested entity
  $options = array_merge(['full_format' => $instance->needFullFormat()], $options);
    return AddressEntity::represent($instance->address, $options);
  });
  self::expose('email', ['if' => ['type' => 'full']]);
}

// api/AddressEntity.php
// the new option could be retrieved in options array for conditional exposure
self::expose('state', ['if' => 'full_format']);
self::expose('city', ['if' => 'full_format']);
self::expose('street', function($instance, $options) {
  // the new option could be retrieved in options hash for runtime exposure
  return $options['full_format'] ? $instance->full_street_name : $instance->simple_street_name;
});
```
**Notice**: In the above code, you should pay attention to [**Safe Exposure**](#safe-exposure) yourself. For example, `$instance->address` might be `null` and it is better to expose it as null directly.

#### Attribute Path Tracking

Sometimes, especially when there are nested attributes, you might want to know which attribute
is being exposed. For example, some APIs allow users to provide a parameter to control which fields
will be included in (or excluded from) the response.

PhpGrape\Entity can track the path of each attribute, which you can access during conditions checking
or runtime exposure via `$options['attr_path']`.

The attribute path is an array. The last item of this array is the name (alias) of current attribute.
If the attribute is nested, the former items are names (aliases) of its ancestor attributes.

Example:

```php
class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::expose('user');  // path is ['user']
    self::expose('foo', ['as' => 'bar']);  // path is ['bar']
    self::expose('a', function() {
      self::expose('b', ['as' => 'xx'], function() {
      self::expose('c');  // path is ['a', 'xx', 'c']
      });
    });
  }
}
```

### Using Entities

Example of API controller.

```php
class ApiController
{
  use UserHelpers;  
  
  public function statuses()
  {
    $statuses = Status::all();
    $type = $this->user()->isAdmin() ? 'full' : 'default';
    $representation = StatusEntity::represent($statuses, ['type' => $type]);

    // PhpGrape\Entity implements JsonSerializable
    return response()->json($representation, 200);
  }
}
```

### JSON and XML formats

PhpGrape\Entity implements JsonSerializable, so calling `json_encode` will automatically serialize your entity to JSON.
It'll work in any circumstances (see [Using Entities](#using-entities)):
```php
json_encode(new MyEntity($myObj))
json_encode(MyEntity::represent($myObj))
```

2 helpers are also presents on PhpGrape\Entity:
- toJson
- toXml

So you can do:
```php
new MyEntity($myObj)->toJson();
new MyEntity($myObj)->toXml();
```

Note: When `::represent` returns a single entity, you'll also be able to call these methods. It means it won't work it you set a `root` key or if you do not use `presentCollection` properly when presenting a collection (see [Collection Exposure](#collection-exposure))

### Key transformer

Most of the time backend languages use different naming conventions than frontend ones, so you often end up using helpers to convert your data keys case.

`transformKeys` helps you deal with that:

```php
Entity::transformKeys(function($key) {
   return mb_strtoupper($key);
});
```

As a bonus, 2 case helpers are already included: `camel` & `snake` (handling 'UTF-8' unicode characters)

Example :
```php
Entity::transformKeys('camel');

class MyEntity extends Entity
{
  use EntityTrait;

  private static function initialize()
  {
    self::root('current_user');
    self::expose('first_name', ['documentation' => ['desc' => 'foo']]);
  }
}

$representation = MyEntity::represent(['first_name' => 'John']);
json_encode($representation); // { currentUser: { firstName: 'John' } }
json_encode(MyEntity::documentation()); // { firstName: { desc: 'foo' } }
```

### Adapters

For many reasons, you might need to access your properties / methods in a certain way. Or redefine the field lookup priority for instance (see [Basic Exposure](#basic-exposure)). PhpGrape\Entity lets you write your own adapter depending on your needs.

Adapter structure:

```php
// Entity used is binded to $this 
//   so you have access to $this->object and $this->options
Entity::setPropValueAdapter('MyAdapter', [
  'condition' => function ($prop, $safe) {
    $model = 'MyProject\MyModel';
    return $this->object instanceof $model;
  },
  'getPropValue' => function ($prop, $safe, $cache) {
    $class = get_class($this->object);
  
    // you can use $cache to speed up future lookups
    if ($cache('get', $class, $prop)) return $this->object->{$prop};
  
    if ($this->object->hasAttribute($prop)) {
      $cache('set', $class, $prop);
      return $this->object->{$prop};
    }
  
    // Prop not found
    return $this->handleMissingProperty($prop, $safe);
  }
]);
```

To remove an adapter, simply set it to null:
```php
Entity::setPropValueAdapter('MyAdapter', null);
```

**NOTE**: adapter names are unique. Using the same name will override previous adapter.

#### Laravel / Eloquent adapter

Eloquent relies massively on the magic `__get` method. Unfortunately, no Exception is thrown in case you access an undefined property, which is quite inconvenient in some situations. It doesn't help either with options like `safe` or `expose_null`.

To fix this, and to enjoy all the great PhpGrape\Entity features, an `Eloquent` adapter's been included. You'll still be able to use magic attributes, mutated attributes and access relations in your exposures. No more typo allowed though!

Works with all Laravel versions!

Note: when creating a model, if all attributes are not passed, you'll need to call the `fresh` method in order to retrieve them.

## Testing with Entities

Test API request/response as usual.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT License. See [LICENSE](LICENSE) for details.
