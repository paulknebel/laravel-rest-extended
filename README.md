
The `laravel-rest-extended` packages provides a `Restful` trait, which complements Api controllers with some useful features.
It makes heavy use of Eloquent and Fractal Transformers - The package assumes your data is well defined using these packages.

The documentation lacking at the moment. Please stand by.

 ## Features
 - Filter data  `/resources?type={type}` (Note: simplified)
 - Filter data subsets, with dot notation `/api/v1/resources?_filter[subset.type]={type}`
 - Filter data, with operators `/api/v1/resources?_filter[title][IN]=Foo&_filter[title][IN]=Bar`
 - Pagination `/api/v1/resources?_p=1&_pp=5` (5 per page, page 1)
 - Ordering data `/api/v1/resources?_order=date_created`
 - Simple inverse ordering `/api/v1/resources?_order=-date_created`
 - Ability to filter data post-query (Take the weight away from the SQL server, for whatever reason)

 
 ## Roadmap
The package is at a very early stage right now. I currently intend to add features as I need them. 

 - Add tests (I've misplaced them somewhere)
 - Remove `laravel/laravel` as a dependancy - Collections and GuzzleHttp will suffix 
 - Improve documentation
 - Finish mapped methods to `RestfulActions`
 - Configuration of `RestfulActions` so that `Restful` does not need to be used

 ## Install
 You can install the package via composer:
 ```
 composer require paulknebel/laravel-rest-extended
 ```
 
 ## Getting started

The easiest way to get started is to use the `RestfulActions` trait. It provides some actions that directly map to Laravel's own "resource" actions

 ```php
 use PaulKnebel\LaravelRestExtended\Restful;

class ResourceApiController extends Controller {
	use RestfulActions;

	public function __construct(Manager $fractal, Model $model, Transformer $transformer, Request $request) 
	{
		$this->setRequest($request)->setFractal($fractal)->setRootModel($model);
	}

	public function index()
	{
		return $this->restfulIndexAction();
	}

    public function show($id) 
    {
       return $this->restfulShowAction($id);
    }
}
 ```

Alternatively, you can directly use the `Restful` trait, which provides more granular features of the REST package.

```php
use paulknebel\LaravelRestExtended\Restful;

class MyApiController extends Controller 
{
	public function index() {
		// Initiate query object
		$query = \App\Models\MyModel::all();
	
		// Pass query through methods which
		$this->applyFiltersToQuery($query, $this->request);
		$this->applyOrderToQuery($query, $this->request);
		$this->applyPagination($query, $this->request);

		// Retrieve / Respond
		$data = $query->get();
		return $this->mutateData($data, new Collection())->toArray();
	}
}
```

 ### Operators available
EQ, NOT, GT, GTE, LT, LTE, LIKE
