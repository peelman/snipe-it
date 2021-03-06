@extends('backend/layouts/default')

{{-- Page title --}}
@section('title')
@if (Input::get('Pending') || Input::get('Undeployable') || Input::get('RTD')  || Input::get('Deployed'))
	@if (Input::get('Pending'))
		Pending
	@elseif (Input::get('RTD'))
		Ready to Deploy
	@elseif (Input::get('Undeployable'))
		Un-deployable
	@elseif (Input::get('Deployed'))
		Deployed
	@endif
@else
		All
@endif

Assets ::
@parent
@stop

{{-- Page content --}}
@section('content')
<div class="page-header">
		<div class="pull-right">
			<a href="{{ route('create/hardware') }}" class="btn-flat success"><i class="icon-plus-sign icon-white"></i> Create New</a>
		</div>

		<h3>
		@if (Input::get('Pending') || Input::get('Undeployable') || Input::get('RTD')  || Input::get('Deployed'))
			@if (Input::get('Pending'))
				Pending
			@elseif (Input::get('RTD'))
				Ready to Deploy
			@elseif (Input::get('Undeployable'))
				Un-deployable
			@elseif (Input::get('Deployed'))
				Deployed
			@endif
		@else
			All
		@endif
				Assets
		</h3>

</div>

@if ($assets->count() > 0)


<table id="example">
	<thead>
		<tr role="row">
			<th class="span2" bSortable="true">@lang('admin/hardware/table.asset_tag')</th>
			<th class="span2" bSortable="true"><span class="line"></span>@lang('admin/hardware/table.title')</th>
			<th class="span2" bSortable="true"><span class="line"></span>@lang('admin/hardware/table.serial')</th>
			@if (Input::get('Pending') || Input::get('Undeployable') || Input::get('RTD'))
			<th class="span2" bSortable="true">Status</th>
			@else
			<th class="span2" bSortable="true">@lang('admin/hardware/table.checkoutto')</th>
			<th class="span2" bSortable="true">@lang('admin/hardware/table.location')</th>
			@endif

			<th class="span1">@lang('admin/hardware/table.change')</th>
			<th class="span2" bSortable="false">@lang('table.actions')</th>
		</tr>
	</thead>
	<tbody>

		@foreach ($assets as $asset)
		<tr>
			<td><a href="{{ route('view/hardware', $asset->id) }}">{{ $asset->asset_tag }}</a></td>
			<td><a href="{{ route('view/hardware', $asset->id) }}">{{ $asset->name }}</a></td>
			<td>{{ $asset->serial }}</td>
			@if (Input::get('Pending') || Input::get('Undeployable') || Input::get('RTD'))
				<td>
					@if (Input::get('Pending'))
						Pending
					@elseif (Input::get('RTD'))
						Ready to Deploy
					@elseif (Input::get('Undeployable'))
						@if ($asset->assetstatus)
						{{ $asset->assetstatus->name }}
						@endif

					@endif
				</td>
			@else
				<td>
				@if ($asset->assigneduser)
					<a href="{{ route('view/user', $asset->assigned_to) }}">
					{{ $asset->assigneduser->fullName() }}
					</a>
				@endif
				</td>
				<td>
				@if ($asset->assigneduser && $asset->assetloc)
						{{ $asset->assetloc->name }}
				@else
					@if ($asset->assetstatus)
						{{ $asset->assetstatus->name }}
					@endif
				@endif
				</td>

			@endif

			<td>
			@if ($asset->assigned_to != 0)
				<a href="{{ route('checkin/hardware', $asset->id) }}" class="btn-flat info">Checkin</a>
			@else
				<a href="{{ route('checkout/hardware', $asset->id) }}" class="btn-flat success">Checkout</a>
			@endif
			</td>
			<td nowrap="nowrap">
				<a href="{{ route('update/hardware', $asset->id) }}" class="btn-flat white">@lang('button.edit')</a>
				<a data-html="false" class="btn-flat danger delete-asset" data-toggle="modal" href="{{ route('delete/hardware', $asset->id) }}" data-content="Are you sure you wish to delete this asset?" data-title="Delete {{ htmlspecialchars($asset->asset_tag) }}?" onClick="return false;">@lang('button.delete')</a>
			</td>
		</tr>
		@endforeach
	</tbody>
</table>
@else
<div class="col-md-6">
	<div class="alert alert-info alert-block">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<i class="icon-info-sign"></i>
		There are no results for your query.
	</div>
</div>

@endif


@stop
