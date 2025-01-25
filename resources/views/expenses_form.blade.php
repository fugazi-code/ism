@extends('admin_layout')

@section('content')
<div id='app' class="container-fluid">

    <!-- Content Row -->
    <div class="row">

        <div class="col-lg-12 mb-4">
            <!-- Approach -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Expenses Form</h6>
                </div>
                <div class="card-body">
                    <div>
                        <div class="row">
                            <div class="col-md-2 mb-2">
                                <label>Expenses No.</label>
                                <input class="form-control" v-model='overview.expenses_no'>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label>Cost Center</label>
                                <input class="form-control" name="cost_center" v-model='overview.cost_center'>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label>Expense Date</label>
                                <input type="date" class="form-control" v-model='overview.expense_date'>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label>Person Assigned</label>
                                <input class="form-control" name="person_assigned" v-model='overview.person_assigned'>
                            </div>
                            <div class="col-md-3  mb-2">
                                <label>Total Amount</label>
                                <input type="number" class="form-control" v-model='overview.total_amount'>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label>SI Number</label>
                                <input class="form-control" v-model='overview.si_no'>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label>DR Number</label>
                                <input class="form-control" v-model='overview.dr_no'>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Description</label>
                                <input class="form-control" name="description" v-model='overview.description'/>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="form-group">
                                    <label>VAT Type</label>
                                    <select type="text" class="form-control form-control-sm" v-model="overview.vat_type">
                                        <option value="">-- Select Options --</option>
                                        <option value="VAT EX">VAT EX</option>
                                        <option value="VAT INC">VAT INC</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label>Remarks</label>
                                <input class="form-control" name="remarks" v-model='overview.remarks'/>
                            </div>
                            <div class="col-md-12 mb-2 mt-2">
                                <button v-if="overview.id" type="button" class="btn btn-block btn-primary" @click="update()">Update</button>
                                <button v-else type="button" class="btn btn-block btn-primary" @click="save()">Submit</button>
                                <a href="{{ route('expenses') }}" class="btn btn-block btn-secondary" >Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script>
        const app = new Vue({
            el: '#app',
            data() {
                return {
                    dt: null,
                    overview:
                    @isset($overview)
                        {!! $overview !!}
                    @else
                        {
                            expenses_no: "{{ $expenses_no }}",
                            cost_center: "",
                            description: "",
                            person_assigned: "",
                            total_amount: 0,
                            expense_date: "",
                            si_no: "",
                            dr_no: "",
                            remarks: "",
                            vat_type: "",
                            created_by: {{ auth()->id() }}
                        }
                    @endisset
                }
            },
            methods: {
                update() {
                    var $this = this;
                    $.ajax({
                        url: '{{ route('expenses.update') }}',
                        method: 'POST',
                        data: $this.overview,
                        success: function(value) {
                            Swal.fire(
                                'Good job!',
                                'Operation is successful.',
                                'success'
                            ).then((result) => {
                                if (result.value) {
                                    window.location = '{{ route('expenses') }}'
                                }
                            })
                        },
                        error(e) {
                            console.log(e);
                            Swal.fire(
                                e.statusText,
                                e.responseJSON.message,
                                'warning'
                            );
                        }
                    });
                },
                save() {
                    var $this = this;

                    $.ajax({
                        url: '{{ route('expenses.store') }}',
                        method: 'POST',
                        data: $this.overview,
                        success: function(value) {
                            Swal.fire(
                                'Good job!',
                                'Operation is successful.',
                                'success'
                            ).then((result) => {
                                if (result.value) {
                                    window.location = '{{ route('expenses') }}'
                                }
                            })
                        },
                        error(e) {
                            console.log(e);
                            Swal.fire(
                                e.statusText,
                                e.responseJSON.message,
                                'warning'
                            );
                        }
                    });
                }
            },
            mounted() {
                var $this = this;
                $('[name="description"]').autocomplete({
                    minLength: 2,
                    select: function (event, ui) {
                        $this.overview.description = ui.item.value;
                    },
                    source: {!! $expenses_list->unique('description')->where('description', '<>', null)->pluck('description') !!}
                });

                $('[name="remarks"]').autocomplete({
                    minLength: 2,
                    select: function (event, ui) {
                        $this.overview.remarks = ui.item.value;
                    },
                    source: {!! $expenses_list->unique('remarks')->where('remarks', '<>', null)->pluck('remarks') !!}
                });

                $('[name="cost_center"]').autocomplete({
                    minLength: 2,
                    select: function (event, ui) {
                        $this.overview.cost_center = ui.item.value;
                    },
                    source: {!! $expenses_list->unique('cost_center')->where('cost_center', '<>', null)->pluck('cost_center') !!}
                });

                $('[name="person_assigned"]').autocomplete({
                    minLength: 2,
                    select: function (event, ui) {
                        $this.overview.person_assigned = ui.item.value;
                    },
                    source: {!! $expenses_list->unique('person_assigned')->where('person_assigned', '<>', null)->pluck('person_assigned') !!}
                });
            }
        });
    </script>
@endsection
