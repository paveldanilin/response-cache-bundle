# A set of Symfony controller cache annotations


- `@Cacheable`
- `@CacheEvict`


## Install

`composer require paveldanilin/response-cache-bundle`


## Configuration

```yaml
# Default values
response_cache:
  lock:
    factory: 'lock.response_cache.factory'
  controller:
    dir: '%kernel.project_dir%/src'
```

`lock.factory`: A lock factory service, [read more](https://symfony.com/doc/current/components/lock.html) about lock component configuration.

`controller.dir`: A controller directory for annotation scan.


## Usage

### @Cacheable
`@Cacheable(pool=<cache.pool.name>, key=<item.key.name>, ttl=<expires.after.sec>, condition=<request.condition.expression>)`

The simplest way to enable caching behavior for a method is to demarcate it with @Cacheable annotation.
The default parameters:
- `key` - a concatenated class name with a method name: `App\Controller\MyController_doHeavyComputation`
- `pool` - `cache.app`
- `ttl` - null (never gets expired)
- `condition` - null

```php
/**
  * @Cacheable()
  * @Route("/api/v1/work", methods={"GET"}, name="do.work")
  * @return JsonResponse
  */
public function doWork(Request $request): JsonResponse
{
    $data = doHeavyComputations();
    return new JsonResponse($data);
}
```

#### Key
There are two types of keys:
- static
- dynamic


**Static key**

The static key is whether an empty value `<className_methodName>` or a string `'<static_key>'`.

For example: `@Cacheable()` or `@Cacheable(key='my_item')`


**Dynamic key**

The dynamic key starts from the '#' sign i.e. `#<expression>`.

[Symfony expression language](https://symfony.com/doc/current/components/expression_language/syntax.html) is used for the dynamic key computation.

The following variables can be used in the expression:
- `request` - the inbound request object (`\Symfony\Component\HttpFoundation\Request`)
- `query` - query parameters (`$request->query->all()`)
- `params` - route parameters (`$request->attributes->all()['_route_params']`)

For example: `@Cacheable(key='#request.getMethod()~request.getRequestUri()')`


#### Pool

You can [define](https://symfony.com/doc/current/cache.html) your own cache pool and use it instead of the default `cache.app`.


#### TTL

Time to live of the cache item in seconds.
If not defined will be used a pool default value.


#### Condition

If we want more control over when the annotation is active, we can parameterize @Cacheable with a condition parameter that
takes a Symfony expression and ensures that the results are cached based on evaluating that expression.


### @CacheEvict
`@CacheEvict(pool=<cache.pool.name>, key=<item.key.name>)`
