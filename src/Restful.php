<?php

namespace PaulKnebel\LaravelRestExtended;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use League\Fractal\Resource\Collection;

/**
 * A set of tools that interpret the request into a query
 * @property Request request
 * @property Model query        Query builder
 * @property array filters      Filters determine by request
 * @property Collection data    Query results
 * @property Paginator paginator Pagination object
 * @package App\Traits
 */
trait Restful
{
    /** @var array Operators that are supported */
    private static $acceptedOperators = ['NOT', 'GT', 'GTE', 'LT', 'LTE', 'EQ', 'LIKE'];
    private $defaultOrderBy = '';
    private static $filterableFields = null;

    /**
     * Determine and normalise the filters required for the Query Builder Object
     * @param Request $request
     * @return array        Calculated list of filters
     */
    private function getQueryFilters(Request $request)
    {
        $filters = [];

        // Need to manually get query string to prevent it from converting dots to underscores
        $parameters = $request->query->all();

        // PHP replaces dots with underscores so we workaround this by using _filter
        // @note This currently doesn't work anyway because it uses table to filter, rather than relation
        if (isset($parameters['_filter'])) {
            $parameters += $parameters['_filter'];
            unset($parameters['_filter']);
        }

        foreach ($parameters as $attribute => $values) {
            // Underscores are reserved - use them in "_filter" if actually needed
            if (starts_with($attribute, '_')) {
                continue;
            }

            // Normalise values into an array
            if (is_string($values)) {
                $values = (array) $values;
            }

            // Specified operators
            foreach (self::$acceptedOperators as $op) {
                if (isset($values[$op])) {
                    $filters[] = [$attribute, $op, (array) $values[$op]];
                    unset($values[$op]); // Forget it so we don't confuse the simple filter
                }
            }

            // Default operator = EQ
            if (is_array($values) && count($values)) {
                $filters[] = [$attribute, 'EQ', (array) $values];
            }
        }

        $this->filters = $filters;
        return $this->filters;
    }

    /**
     * Applies filters to Query Builder based on the Request Parameters
     * @note We could possibly allow "=", etc. but due to url encoding I think we should encourage readibility
     * @param Request $request
     * @param $query
     * @return mixed
     */
    private function applyFiltersToQuery($query, Request $request)
    {
        $filters = $this->getQueryFilters($request);

        foreach ($filters as list($attribute, $operator, $values)) {
            if (!$this->isFieldFilterable($attribute)) {
                continue;
            }

            switch ($operator) {
                case 'EQ':
                    $query->whereIn($attribute, $values);
                    break;
                case 'NOT':
                    $query->whereNotIn($attribute, $values);
                    break;
                case 'GT':
                case 'GTE':
                    $query->where($attribute, $operator === 'GT' ? '>' : '>=', max($values));
                    break;
                case 'LT':
                case 'LTE':
                    $query->where($attribute, $operator === 'LT' ? '<' : '<=', min($values));
                    break;
                case 'LIKE':
                    foreach ($values as $v) {
                        $query->where($attribute, 'LIKE', '%' . $v . '%');
                    }
                    break;
            }
        }
    }

    /**
     * Determine and apply order of query based on "_order"
     * @param Model $query
     * @param Request $request
     */
    private function applyOrderToQuery($query, Request $request)
    {
        $orderBy = $request->input('_order', $this->defaultOrderBy);

        if (!$orderBy) {
            return;
        }

        // Negate direction with a "-" prefix
        $orderDirection = starts_with($orderBy, '-') ? 'DESC' : 'ASC';
        $orderBy = ltrim($orderBy, '-');

        $query->orderBy($orderBy, $orderDirection);
    }

    /**
     * Create and apply paginator to resultset
     * @param $query
     * @param Request $request
     */
    private function applyPagination($query, Request $request)
    {
        $this->paginator = $query->paginate(null, ['*'], '_page');
        $this->paginator->appends(array_diff_key(
            $this->request->input(),
            array_flip(['_page'])
        ));
    }

    /**
     * Determine whether field can be filtered
     * @note Uses $fillable parameter, because it's built into Eloquent and roughly the same logic
     * @todo Does not consider filtering sub-content
     * @param string $field
     * @return bool
     */
    private function isFieldFilterable($field)
    {
        return in_array($field, (array) $this->rootModel['fillable'], true);
    }


}