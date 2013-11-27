<?php namespace Controllers\Admin;

use AdminController;
use Input;
use Lang;
use Asset;
use Statuslabel;
use User;
use Setting;
use Redirect;
use DB;
use Actionlog;
use Model;
use Depreciation;
use Sentry;
use Str;
use Validator;
use View;

class AssetsController extends AdminController {

	/**
	 * Show a list of all the assets.
	 *
	 * @return View
	 */

	public function getIndex()
	{
		// Grab all the assets

		// Filter results
		if (Input::get('Pending'))
		{
			$assets = Asset::orderBy('asset_tag', 'ASC')->whereNull('status_id','and')->where('assigned_to','=','0')->where('physical', '=', 1)->get();
		}
		else if (Input::get('RTD'))
		{
			$assets = Asset::orderBy('asset_tag', 'ASC')->where('status_id', '=', 0)->where('assigned_to','=','0')->where('physical', '=', 1)->get();
		}
		else if (Input::get('Undeployable'))
		{
			$assets = Asset::orderBy('asset_tag', 'ASC')->where('status_id', '>', 1)->where('physical', '=', 1)->get();
		}
		else if (Input::get('Deployed'))
		{
			$assets = Asset::orderBy('asset_tag', 'ASC')->where('status_id', '=', 0)->where('assigned_to','>','0')->where('physical', '=', 1)->get();
		}
		else
		{
			$assets = Asset::orderBy('asset_tag', 'ASC')->where('physical', '=', 1)->get();
		}

		// Paginate the users
		/**$assets = $assets->paginate(Setting::getSettings()->per_page)
			->appends(array(
				'Pending' => Input::get('Pending'),
				'RTD' => Input::get('RTD'),
				'Undeployable' => Input::get('Undeployable'),
				'Deployed' => Input::get('Deployed'),
			));
		**/

		return View::make('backend/assets/index', compact('assets'));
	}

	public function getReports()
	{
		// Grab all the assets
		$assets = Asset::orderBy('created_at', 'DESC')->get();
		return View::make('backend/reports/index', compact('assets'));
	}

	/**
	 * Asset create.
	 *
	 * @return View
	 */
	public function getCreate()
	{
		// Grab the dropdown list of models
		$model_list = array('' => '') + Model::lists('name', 'id');
		$depreciation_list = array('' => '') + Depreciation::lists('name', 'id');

		// Grab the dropdown list of status
		$statuslabel_list = array('' => 'Pending') + array('1' => 'Ready to Deploy') + Statuslabel::lists('name', 'id');

		return View::make('backend/assets/edit')->with('model_list',$model_list)->with('statuslabel_list',$statuslabel_list)->with('depreciation_list',$depreciation_list)->with('asset',new Asset);

	}


	/**
	 * Asset create form processing.
	 *
	 * @return Redirect
	 */
	public function postCreate()
	{

		// get the POST data
		$new = Input::all();

		// create a new model instance
		$asset = new Asset();

		// attempt validation
		if ($asset->validate($new))
		{

			if ( e(Input::get('status_id')) == '') {
				$asset->status_id =  NULL;
			} else {
				$asset->status_id = e(Input::get('status_id'));
			}

			if (e(Input::get('warranty_months')) == '') {
				$asset->warranty_months =  NULL;
			} else {
				$asset->warranty_months        = e(Input::get('warranty_months'));
			}

			if (e(Input::get('purchase_cost')) == '') {
				$asset->purchase_cost =  NULL;
			} else {
				$asset->purchase_cost        = e(Input::get('purchase_cost'));
			}

			if (e(Input::get('purchase_date')) == '') {
				$asset->purchase_date =  NULL;
			} else {
				$asset->purchase_date        = e(Input::get('purchase_date'));
			}

			// Save the asset data
			$asset->name            		= e(Input::get('name'));
			$asset->serial            		= e(Input::get('serial'));
			$asset->model_id           		= e(Input::get('model_id'));
			$asset->order_number            = e(Input::get('order_number'));
			$asset->notes            		= e(Input::get('notes'));
			$asset->asset_tag            	= e(Input::get('asset_tag'));
			$asset->user_id          		= Sentry::getId();
			$asset->assigned_to          		= '0';
			$asset->archived          			= '0';
			$asset->physical            		= '1';
			$asset->depreciate          		= '0';


			// Was the asset created?
			if($asset->save())
			{
				// Redirect to the asset listing page
				return Redirect::to("admin")->with('success', Lang::get('admin/assets/message.create.success'));
			}
		}
		else
		{
			// failure
			$errors = $asset->errors();
			return Redirect::back()->withInput()->withErrors($errors);
		}

		// Redirect to the asset create page with an error
		return Redirect::to('assets/create')->with('error', Lang::get('admin/assets/message.create.error'));


	}

	/**
	 * Asset update.
	 *
	 * @param  int  $assetId
	 * @return View
	 */
	public function getEdit($assetId = null)
	{
		// Check if the asset exists
		if (is_null($asset = Asset::find($assetId)))
		{
			// Redirect to the asset management page
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.does_not_exist'));
		}

		// Grab the dropdown list of models
		$model_list = array('' => '') + Model::lists('name', 'id');

		// Grab the dropdown list of status
		$statuslabel_list = array('' => 'Pending') + array('1' => 'Ready to Deploy') + Statuslabel::lists('name', 'id');

		// get depreciation list
		$depreciation_list = array('' => '') + Depreciation::lists('name', 'id');

		return View::make('backend/assets/edit', compact('asset'))->with('model_list',$model_list)->with('depreciation_list',$depreciation_list)->with('statuslabel_list',$statuslabel_list);
	}


	/**
	 * Asset update form processing page.
	 *
	 * @param  int  $assetId
	 * @return Redirect
	 */
	public function postEdit($assetId = null)
	{
		// Check if the asset exists
		if (is_null($asset = Asset::find($assetId)))
		{
			// Redirect to the asset management page with error
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.does_not_exist'));
		}


		// Declare the rules for the form validation
		$rules = array(
		'name'   => 'required|min:3',
		'asset_tag'   => 'required|alpha_dash|min:3',
		'model_id'   => 'required',
		'serial'   => 'required|alpha_space|min:3',
		'warranty_months'   => 'integer',
		'notes'   => 'alpha_space',
    	);

		// Create a new validator instance from our validation rules
		$validator = Validator::make(Input::all(), $rules);

		// If validation fails, we'll exit the operation now.
		if ($validator->fails())
		{
			// Ooops.. something went wrong
			return Redirect::back()->withInput()->withErrors($validator);
		}

			if ( e(Input::get('status_id')) == '') {
				$asset->status_id =  NULL;
			} else {
				$asset->status_id = e(Input::get('status_id'));
			}

			if (e(Input::get('warranty_months')) == '') {
				$asset->warranty_months =  NULL;
			} else {
				$asset->warranty_months        = e(Input::get('warranty_months'));
			}

			if (e(Input::get('purchase_cost')) == '') {
				$asset->purchase_cost =  NULL;
			} else {
				$asset->purchase_cost        = e(Input::get('purchase_cost'));
			}

			if (e(Input::get('purchase_date')) == '') {
				$asset->purchase_date =  NULL;
			} else {
				$asset->purchase_date        = e(Input::get('purchase_date'));
			}


			// Update the asset data
			$asset->name            		= e(Input::get('name'));
			$asset->serial            		= e(Input::get('serial'));
			$asset->model_id           		= e(Input::get('model_id'));
			$asset->order_number            = e(Input::get('order_number'));
			$asset->asset_tag           	= e(Input::get('asset_tag'));
			$asset->notes            		= e(Input::get('notes'));
			$asset->physical            		= '1';

			// Was the asset updated?
			if($asset->save())
			{
				// Log the Edit
				$logaction = new Actionlog();
				$logaction->asset_id = $asset->id;
				$logaction->checkedout_to = null;
				$logaction->asset_type = null;
				$logaction->location_id = null;
				$logaction->user_id = Sentry::getUser()->id;
				$logaction->note = e(Input::get('edit_note'));
				$log = $logaction->logaction('edited');
				
				// Redirect to the new asset page
				return Redirect::to("admin")->with('success', Lang::get('admin/assets/message.update.success'));
			}


		// Redirect to the asset management page with error
		return Redirect::to("assets/$assetId/edit")->with('error', Lang::get('admin/assets/message.update.error'));

	}

	/**
	 * Delete the given asset.
	 *
	 * @param  int  $assetId
	 * @return Redirect
	 */
	public function getDelete($assetId)
	{
		// Check if the asset exists
		if (is_null($asset = Asset::find($assetId)))
		{
			// Redirect to the asset management page with error
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.not_found'));
		}

		if (isset($asset->assigneduser->id) && ($asset->assigneduser->id!=0)) {
			// Redirect to the asset management page
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.assoc_users'));
		} else {
			// Delete the asset
			$asset->delete();

			// Redirect to the asset management page
			return Redirect::to('admin')->with('success', Lang::get('admin/assets/message.delete.success'));
		}



	}

	/**
	* Check out the asset to a person
	**/
	public function getCheckout($assetId)
	{
		// Check if the asset exists
		if (is_null($asset = Asset::find($assetId)))
		{
			// Redirect to the asset management page with error
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.not_found'));
		}

		// Get the dropdown of users and then pass it to the checkout view
		$users_list = array('' => 'Select a User') + DB::table('users')->select(DB::raw('concat(first_name," ",last_name) as full_name, id'))->lists('full_name', 'id');

		//print_r($users);
		return View::make('backend/assets/checkout', compact('asset'))->with('users_list',$users_list);

	}

	/**
	* Check out the asset to a person
	**/
	public function postCheckout($assetId)
	{
		// Check if the asset exists
		if (is_null($asset = Asset::find($assetId)))
		{
			// Redirect to the asset management page with error
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.not_found'));
		}

		$assigned_to = e(Input::get('assigned_to'));


		// Declare the rules for the form validation
		$rules = array(
			'assigned_to'   => 'required|min:1',
			'note'   => 'alpha_space',
		);

		// Create a new validator instance from our validation rules
		$validator = Validator::make(Input::all(), $rules);

		// If validation fails, we'll exit the operation now.
		if ($validator->fails())
		{
			// Ooops.. something went wrong
			return Redirect::back()->withInput()->withErrors($validator);
		}


		// Check if the user exists
		if (is_null($assigned_to = User::find($assigned_to)))
		{
			// Redirect to the asset management page with error
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.user_does_not_exist'));
		}

		// Update the asset data
		$asset->assigned_to            		= e(Input::get('assigned_to'));

		// Was the asset updated?
		if($asset->save())
		{
			$logaction = new Actionlog();
			$logaction->asset_id = $asset->id;
			$logaction->checkedout_to = $asset->assigned_to;
			$logaction->asset_type = 'hardware';
			$logaction->location_id = $assigned_to->location_id;
			$logaction->user_id = Sentry::getUser()->id;
			$logaction->note = e(Input::get('note'));
			$log = $logaction->logaction('checkout');

			// Redirect to the new asset page
			return Redirect::to("admin")->with('success', Lang::get('admin/assets/message.checkout.success'));
		}

		// Redirect to the asset management page with error
		return Redirect::to("assets/$assetId/checkout")->with('error', Lang::get('admin/assets/message.checkout.error'));
	}


	/**
	* Check the asset back into inventory
	*
	* @param  int  $assetId
	* @return View
	**/
	public function getCheckin($assetId)
	{
		// Check if the asset exists
		if (is_null($asset = Asset::find($assetId)))
		{
			// Redirect to the asset management page with error
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.not_found'));
		}

		return View::make('backend/assets/checkin', compact('asset'));
	}


	/**
	* Check in the item so that it can be checked out again to someone else
	*
	* @param  int  $assetId
	* @return View
	**/
	public function postCheckin($assetId)
	{
		// Check if the asset exists
		if (is_null($asset = Asset::find($assetId)))
		{
			// Redirect to the asset management page with error
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.not_found'));
		}

		if (!is_null($asset->assigned_to)) {
		 	$user = User::find($asset->assigned_to);
		}

		$logaction = new Actionlog();
		$logaction->checkedout_to = $asset->assigned_to;

		// Update the asset data to null, since it's being checked in
		$asset->assigned_to            		= '0';

		// Was the asset updated?
		if($asset->save())
		{

			$logaction->asset_id = $asset->id;
			$logaction->location_id = NULL;
			$logaction->asset_type = 'hardware';
			$logaction->note = e(Input::get('note'));
			$logaction->user_id = Sentry::getUser()->id;
			$log = $logaction->logaction('checkin from');

			// Redirect to the new asset page
			return Redirect::to("admin")->with('success', Lang::get('admin/assets/message.checkin.success'));
		}

		// Redirect to the asset management page with error
		return Redirect::to("admin")->with('error', Lang::get('admin/assets/message.checkin.error'));
	}


	/**
	*  Get the asset information to present to the asset view page
	*
	* @param  int  $assetId
	* @return View
	**/
	public function getView($assetId = null)
	{
		$asset = Asset::find($assetId);

		if (isset($asset->id)) {
				return View::make('backend/assets/view', compact('asset'));
		} else {
			// Prepare the error message
			$error = Lang::get('admin/assets/message.does_not_exist', compact('id' ));

			// Redirect to the user management page
			return Redirect::route('assets')->with('error', $error);
		}

	}

	/**
	 * Asset update.
	 *
	 * @param  int  $assetId
	 * @return View
	 */
	public function getClone($assetId = null)
	{
		// Check if the asset exists
		if (is_null($asset = Asset::find($assetId)))
		{
			// Redirect to the asset management page
			return Redirect::to('admin')->with('error', Lang::get('admin/assets/message.does_not_exist'));
		}

		// Grab the dropdown list of models
		$model_list = array('' => '') + Model::lists('name', 'id');

		// Grab the dropdown list of status
		$statuslabel_list = array('' => 'Pending') + array('1' => 'Ready to Deploy') + Statuslabel::lists('name', 'id');

		// get depreciation list
		$depreciation_list = array('' => '') + Depreciation::lists('name', 'id');

		return View::make('backend/assets/clone', compact('asset'))->with('model_list',$model_list)->with('depreciation_list',$depreciation_list)->with('statuslabel_list',$statuslabel_list);
	}


}
