<?php

namespace Joy\VoyagerRelationsTable\Http\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Joy\VoyagerRelationsTable\Services\RelationshipResolver;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\DataType;

trait IndexAction
{
    //***************************************
    //               ____
    //              |  _ \
    //              | |_) |
    //              |  _ <
    //              | |_) |
    //              |____/
    //
    //      Index DataTable our Data Type (B)READ
    //
    //****************************************

    public function index(Request $request)
    {
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $parentSlug = $this->getParentSlug($request);
        $slug       = $this->getSlug($request);
        $id         = $request->id;
        $relation   = $request->relation;

        // GET THE DataType based on the slug
        $parentDataType = Voyager::model('DataType')->where('slug', '=', $parentSlug)->first();
        $dataType       = Voyager::model('DataType')->where('slug', '=', $slug)->firstOrFail();

        // Check permission
        $this->authorize('read', app($parentDataType->model_name));
        $this->authorize('browse', app($dataType->model_name));

        $getter = 'paginate';

        $orderBy         = $request->get('order_by', $dataType->order_column);
        $sortOrder       = $request->get('sort_order', $dataType->order_direction);
        $usesSoftDeletes = false;
        $showSoftDeleted = false;

        // Next Get or Paginate the actual content from the MODEL that corresponds to the slug DataType
        if (strlen($parentDataType->model_name) != 0 && strlen($dataType->model_name) != 0) {
            $parentModel = app($parentDataType->model_name);
            $model       = app($dataType->model_name);
            $parentData  = $parentModel->findOrFail($id);

            $query = app(RelationshipResolver::class)->handle(
                $parentDataType,
                $parentData,
                $relation,
            )->select($dataType->name . '.*');

            if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope' . ucfirst($dataType->scope))) {
                $query->{$dataType->scope}();
            }

            // Use withTrashed() if model uses SoftDeletes and if toggle is selected
            if ($model && in_array(SoftDeletes::class, class_uses_recursive($model)) && Auth::user()->can('delete', app($dataType->model_name))) {
                $usesSoftDeletes = true;

                if ($request->get('showSoftDeleted')) {
                    $showSoftDeleted = true;
                    $query->withTrashed();
                }
            }

            // If a column has a relationship associated with it, we do not want to show that field
            $this->removeRelationshipField($dataType, 'browse');

            $row = $dataType->rows->where('field', $orderBy)->firstWhere('type', 'relationship');
            if ($orderBy && (in_array($orderBy, $dataType->fields()) || !empty($row))) {
                $querySortOrder = (!empty($sortOrder)) ? $sortOrder : 'desc';
                if (!empty($row)) {
                    $query->select([
                        $dataType->name . '.*',
                        'joined.' . $row->details->label . ' as ' . $orderBy,
                    ])->leftJoin(
                        $row->details->table . ' as joined',
                        $dataType->name . '.' . $row->details->column,
                        'joined.' . $row->details->key
                    );
                }

                $query->orderBy($orderBy, $querySortOrder);
            } elseif ($model->timestamps) {
                $query->latest($model::CREATED_AT);
            } else {
                $query->orderBy($model->getKeyName(), 'DESC');
            }
        } else {
            // If Model doesn't exist, get data from table name
            $query = DB::table($dataType->name);
            $model = false;
        }

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($model);

        // Actions
        $actions = [];

        foreach (Voyager::actions() as $action) {
            $action = new $action($dataType, $model);

            if ($action->shouldActionDisplayOnDataType()) {
                $actions[] = $action;
            }
        }

        // Define showCheckboxColumn
        $showCheckboxColumn = false;
        if (Auth::user()->can('delete', app($dataType->model_name))) {
            $showCheckboxColumn = true;
        } else {
            foreach ($actions as $action) {
                if (method_exists($action, 'massAction')) {
                    $showCheckboxColumn = true;
                }
            }
        }

        // Define orderColumn
        $orderColumn = [];
        if ($orderBy) {
            $index       = $dataType->browseRows->where('field', $orderBy)->keys()->first() + ($showCheckboxColumn ? 1 : 0);
            $orderColumn = [[$index, $sortOrder ?? 'desc']];
        }

        // Define list of columns that can be sorted server side
        $sortableColumns = $this->getSortableColumns($dataType->browseRows);

        $view = 'joy-voyager-relations-table::bread.relations-table';

        if (view()->exists("joy-voyager-relations-table::$slug.relations-table")) {
            $view = "joy-voyager-relations-table::$slug.relations-table";
        }

        return Voyager::view($view, compact(
            'actions',
            'parentData',
            'parentDataType',
            'dataType',
            'id',
            'slug',
            'parentSlug',
            'relation',
            'isModelTranslatable',
            'orderBy',
            'orderColumn',
            'sortableColumns',
            'sortOrder',
            'usesSoftDeletes',
            'showSoftDeleted',
            'showCheckboxColumn'
        ));
    }
}
