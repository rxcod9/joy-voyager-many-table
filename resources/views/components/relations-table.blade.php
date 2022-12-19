@php
    $columnDefs = [
        ['targets' => 'dt-not-orderable', 'searchable' =>  false, 'orderable' => false],
    ];
    if($withoutCheckbox) {
        array_push($columnDefs, ['targets' => 'dt-index', 'visible' =>  false]);
    }
    if($withoutActions) {
        array_push($columnDefs, ['targets' => 'dt-actions', 'visible' =>  false]);
    }
@endphp
<div id="wrapper{{ $dataId }}" class="panel panel-bordered panel-sm">
    @if($withLabel)
    <div class="panel-header">
        <h1 class="page-title">
            <i class="{{ $dataType->icon }}"></i> {{ $dataType->getTranslatedAttribute('display_name_plural') }}</a>
        </h1>
    </div>
    @endif
    <div class="panel-body">
        <div class="table-responsive">
            <table id="dataTable{{ $dataId }}" class="table table-hover table-responsive">
                <thead>
                    <tr>
                        @if($showCheckboxColumn)
                            <th class="dt-not-orderable dt-index">
                                <input type="checkbox" class="select_all">
                            </th>
                        @endif
                        @foreach($dataType->browseRows as $row)
                        <th class="dt-col-{{ $row->field }}">
                            {{ $row->getTranslatedAttribute('display_name') }}
                        </th>
                        @endforeach
                        <th class="actions text-right dt-not-orderable dt-actions">{{ __('voyager::generic.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('javascript')

    {{-- Single delete modal --}}
    <div id="modal-wrapper{{ $dataId }}" class="modal modal-danger fade delete_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('voyager::generic.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-trash"></i> {{ __('voyager::generic.delete_question') }} {{ strtolower($dataType->getTranslatedAttribute('display_name_singular')) }}?</h4>
                </div>
                <div class="modal-footer">
                    <form action="#" class="delete_form" method="POST">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm" value="{{ __('voyager::generic.delete_confirm') }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right" data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <script>
        $(document).ready(function () {

            var options = {!! json_encode(
                array_merge([
                    "order" => $orderColumn,
                    "language" => __('voyager::datatable'),
                    "columnDefs" => $columnDefs,
                    "processing" => true,
                    "serverSide" => true,
                    "ajax" => [
                        'url' => route(
                            'voyager.'.$parentDataType->slug.'.relations-table-ajax',
                            [
                                'id' => $id,
                                'relation' => $relation,
                                'slug' => $dataType->slug,
                            ]
                        ),
                        'type' => 'POST',
                    ],
                    "columns" => \dataTypeTableColumns($dataType, $showCheckboxColumn),
                ],
                config('voyager.dashboard.data_tables', []))
            , true) !!};

            options = $.extend(
                options,
                {
                    "drawCallback": function( settings ) {
                        $('#wrapper{{ $dataId }} .select_all').off('click');
                        $('#wrapper{{ $dataId }} .select_all').on('click', function(e) {
                            console.log('clicked');
                            e.stopPropagation();
                            $('#wrapper{{ $dataId }} input[name="row_id"]').prop('checked', $(this).prop('checked')).trigger('change');
                        });
                    }
                }
            );

            var table = $('#wrapper{{ $dataId }} #dataTable{{ $dataId }}').DataTable(options);

            $('#wrapper{{ $dataId }} .select_all').off('click');
            $('#wrapper{{ $dataId }} .select_all').on('click', function(e) {
                console.log('clicked');
                e.stopPropagation();
                $('#wrapper{{ $dataId }} input[name="row_id"]').prop('checked', $(this).prop('checked')).trigger('change');
            });
        });

        var deleteFormAction;
        $('#wrapper{{ $dataId }} #dataTable{{ $dataId }}').on('click', 'td .delete', function (e) {
            $('#modal-wrapper{{ $dataId }} .delete_form')[0].action = '{{ route('voyager.'.$dataType->slug.'.destroy', '__id') }}'.replace('__id', $(this).data('id'));
            $('#modal-wrapper{{ $dataId }}.delete_modal').modal('show');
        });

        $('#wrapper{{ $dataId }} #dataTable{{ $dataId }}').on('change', 'input[name="row_id"]', function (e) {
            var ids = [];
            $('input[name="row_id"]').each(function() {
                if ($(this).is(':checked')) {
                    ids.push($(this).val());
                }
            });
            $('.selected_ids').val(ids);
        });
    </script>
@endpush