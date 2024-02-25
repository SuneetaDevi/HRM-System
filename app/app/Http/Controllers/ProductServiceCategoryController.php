<?php

namespace App\Http\Controllers;

use App\Imports\ProductServiceImport;
use App\Models\Bill;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductService;
use App\Models\ProductServiceCategory;
use App\ProductServiceUnit;
use App\Tax;
use App\Vender;
use Illuminate\Http\Request;

class ProductServiceCategoryController extends Controller
{
    public function index()
    {
        if(\Auth::user()->can('manage constant category'))
        {
            $categories = ProductServiceCategory::where('created_by', '=', \Auth::user()->creatorId())->get();

            return view('productServiceCategory.index', compact('categories'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function create()
    {
        if(\Auth::user()->can('create constant category'))
        {
            $types = ProductServiceCategory::$catTypes;
            array_unshift($types, 'Select Category Type');

            $chart_accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))
                             ->where('created_by', \Auth::user()->creatorId())->get()
                             ->pluck('code_name', 'id');
            $chart_accounts->prepend('Select Account', '');




            return view('productServiceCategory.create', compact('types','chart_accounts'));
        }
        else
        {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {

        if(\Auth::user()->can('create constant category'))
        {

            $validator = \Validator::make(
                $request->all(), [
                                   'name' => 'required|max:200',
                                   'type' => 'required',
                                   'color' => 'required',
                               ]
            );
            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $category             = new ProductServiceCategory();
            $category->name       = $request->name;
            $category->color      = $request->color;
            $category->type       = $request->type;
            $category->chart_account_id  = !empty($request->chart_account)?$request->chart_account:0;
            $category->created_by = \Auth::user()->creatorId();
            $category->save();

            return redirect()->route('product-category.index')->with('success', __('Category successfully created.'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id)
    {

        if(\Auth::user()->can('edit constant category'))
        {
            $types    = ProductServiceCategory::$catTypes;
            $category = ProductServiceCategory::find($id);

            return view('productServiceCategory.edit', compact('category', 'types'));
        }
        else
        {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {

        if(\Auth::user()->can('edit constant category'))
        {
            $category = ProductServiceCategory::find($id);
            if($category->created_by == \Auth::user()->creatorId())
            {
                $validator = \Validator::make(
                    $request->all(), [
                                       'name' => 'required|max:200',
                                       'type' => 'required',
                                       'color' => 'required',
                                   ]
                );
                if($validator->fails())
                {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $category->name  = $request->name;
                $category->color = $request->color;
                $category->type  = $request->type;
                $category->chart_account_id  = !empty($request->chart_account)?$request->chart_account:0;
                $category->save();

                return redirect()->route('product-category.index')->with('success', __('Category successfully updated.'));
            }
            else
            {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if(\Auth::user()->can('delete constant category'))
        {
            $category = ProductServiceCategory::find($id);
            if($category->created_by == \Auth::user()->creatorId())
            {

                if($category->type == 0)
                {
                    $categories = ProductService::where('category_id', $category->id)->first();
                }
                elseif($category->type == 1)
                {
                    $categories = Invoice::where('category_id', $category->id)->first();
                }
                else
                {
                    $categories = Bill::where('category_id', $category->id)->first();
                }

                if(!empty($categories))
                {
                    return redirect()->back()->with('error', __('this category is already assign so please move or remove this category related data.'));
                }

                $category->delete();

                return redirect()->route('product-category.index')->with('success', __('Category successfully deleted.'));
            }
            else
            {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getProductCategories()
    {
        $cat = ProductServiceCategory::getallCategories();
        $all_products = ProductService::getallproducts()->count();

        $html = '<div class="mb-3 mr-2 zoom-in ">
                  <div class="card rounded-10 card-stats mb-0 cat-active overflow-hidden" data-id="0">
                     <div class="category-select" data-cat-id="0">
                        <button type="button" class="btn tab-btns btn-primary">'.__("All Categories").'</button>
                     </div>
                  </div>
               </div>';
        foreach ($cat as $key => $c) {
            $dcls = 'category-select';
            $html .= ' <div class="mb-3 mr-2 zoom-in cat-list-btn">
                          <div class="card rounded-10 card-stats mb-0 overflow-hidden " data-id="'.$c->id.'">
                             <div class="'.$dcls.'" data-cat-id="'.$c->id.'">
                                <button type="button" class="btn tab-btns btn-primary">'.$c->name.'</button>
                             </div>
                          </div>
                       </div>';
        }
        return Response($html);
    }


    public function getAccount(Request $request)
    {

        $chart_accounts =[];
        if($request->type == 'income')
        {
            $chart_accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))
                ->where('type', 4)
                ->where('created_by', \Auth::user()->creatorId())->get()
                ->pluck('code_name', 'id');
        }
        elseif( $request->type == 'expense') {
            $chart_accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))
                ->where('type', 6)
                ->where('created_by', \Auth::user()->creatorId())->get()
                ->pluck('code_name', 'id');
        }
       elseif( $request->type == 'asset') {
           $chart_accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))
               ->where('type', 1)
               ->where('created_by', \Auth::user()->creatorId())->get()
               ->pluck('code_name', 'id');
       }
       elseif($request->type == 'liability'){
           $chart_accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))
               ->where('type', 2)
               ->where('created_by', \Auth::user()->creatorId())->get()
               ->pluck('code_name', 'id');
       }
        elseif($request->type == 'liability'){
            $chart_accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))
                ->where('type', 3)
                ->where('created_by', \Auth::user()->creatorId())->get()
                ->pluck('code_name', 'id');
        }
       else
       {
           $chart_accounts = 0;
       }


        return response()->json($chart_accounts);

    }


}
