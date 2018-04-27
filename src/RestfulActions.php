<?php

namespace PaulKnebel\LaravelRestExtended;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\ResourceAbstract;
use League\Fractal\TransformerAbstract as Transformer;
use League\Fractal\Manager;

/**
 * A set of plug-and-play actions that can be hooked directly into a controller
 * @property Model rootModel
 * @property Transformer rootTransformer
 * @property Request request
 * @package App\Traits
 */
trait RestfulActions
{
    use Restful;

    /** @var Item           Generic placeholder for endpoints that use single item */
    protected $item;

    /** @var Collection     Generic placeholder for endpoints that use collections  */
    protected $collection;

    /**
     * Plug-and-play show action
     * @note If you need more complexity use other methods - this is a one-size-fits-all method
     * @return array    Collection of mutated resources
     */
    private function restfulIndexAction()
    {
        $query = $this->rootModel->newQuery();

        $this->applyFiltersToQuery($query, $this->request);
        $this->applyOrderToQuery($query, $this->request);
        $this->applyPagination($query, $this->request);

        try {
            $this->collection = $query->get();
            return $this->mutateData($this->collection, new Collection())->toArray();
        } catch (QueryException $e) {
            return $this->queryErrorResponse($e);
        }

    }

    /**
     * Plug-and-play show action
     * Returns a single resource
     * @note If you need more complexity use other methods - this is a one-size-fits-all method
     * @param $id
     * @return array    Single mutated resource
     */
    private function restfulShowAction($id)
    {
        try {
            $this->item = $this->rootModel->find($id);
            return $this->mutateData($this->item, new Item())->toArray();
        } catch (QueryException $e) {
            return $this->queryErrorResponse($e);
        }
    }

    /**
     * Plug-and-play create action
     * @return array
     */
    private function restfulStoreAction(array $inputValidationRules = [])
    {
        if($inputValidationRules) {
            $this->validate($this->request, $inputValidationRules);
        }
        
        $attributes = $this->request->input();

        try {
            $this->item = $this->rootModel->create($attributes);
            return $this->mutateData($this->item, new Item())->toArray();
        } catch (QueryException $e) {
            return $this->queryErrorResponse($e);
        }
    }

    /**
     * Plug-and-play UPDATE action (PATCH)
     * @param $id
     * @param array $inputValidationRules
     * @return array
     */
    private function restfulUpdateAction($id, array $inputValidationRules = [])
    {
        if($inputValidationRules) {
            $this->validate($this->request, $inputValidationRules);
        }
        $this->item = $this->rootModel->find($id);

        $attributes = $this->request->input();

        try {
            $this->item->update($attributes);
            return $this->mutateData($this->item, new Item())->toArray();
        } catch (QueryException $e) {
            return $this->queryErrorResponse($e);
        }
    }

    /**
     * Plug-and-play delete action
     * @param $id
     * @return bool
     * @throws \Exception
     */
    private function restfulDestroyAction($id)
    {
        $this->item = $this->rootModel->find($id);

        if ($this->item === null || !is_callable([$this->rootModel, 'delete'])) {
            throw new \InvalidArgumentException('Cannot delete resource');
        }

        try {
            if ($this->item->delete() === true) {
                return [
                    'status' => 'successful'
                ];
            }
        } catch (QueryException $e) {
            return $this->queryErrorResponse($e);
        }
    }

    /**
     * Mutate the data
     * @param Collection $data
     * @param ResourceAbstract $mutateInto
     * @return \League\Fractal\Scope
     */
    private function mutateData($data, ResourceAbstract $mutateInto)
    {
        $this->fractal->parseIncludes($this->request->get('_include', ''));
        $mutated = new $mutateInto($data, $this->rootTransformer);

        if (isset($this->paginator) && $this->paginator !== null) {
            $mutated->setPaginator(new IlluminatePaginatorAdapter($this->paginator));
        }

        return $this->fractal->createData($mutated);
    }

    private function queryErrorResponse(QueryException $e)
    {
        $response['status'] = 'failed';
        $response['error'] = 'Could not perform action';

        // Provide a bit more information for debugging purposes
        if (env('APP_DEBUG')) {
            $response['sql_message'] = $e->getMessage();
            $response['query'] = $e->getSql();
            $response['bindings'] = $e->getBindings();
        }

        return response($response, 501);
    }

    private function setRootModel(Model $model)
    {
        $this->rootModel = $model;
        return $this;
    }

    private function setRootTransformer(Transformer $transformer)
    {
        $this->rootTransformer = $transformer;
        return $this;
    }

    private function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function setFractal(Manager $fractal)
    {
        $this->fractal = $fractal;
        return $this;
    }


}