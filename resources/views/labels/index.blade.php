@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Manage Labels') }}</span>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createLabelModal">
                        Create New Label
                    </button>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (count($labels) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Label</th>
                                        <th>Color</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($labels as $label)
                                        <tr>
                                            <td>
                                                <span class="d-inline-block me-2" style="width: 1rem; height: 1rem; background-color: {{ $label->color }}; border-radius: 50%; border: 1px solid #ccc;"></span>
                                                {{ $label->name }}
                                            </td>
                                            <td>
                                                <code>{{ $label->color }}</code>
                                            </td>
                                            <td>
                                                <a href="{{ route('notes.index', ['label_id' => $label->id]) }}">
                                                    {{ $label->notes->count() }} notes
                                                </a>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-sm edit-label" 
                                                    data-id="{{ $label->id }}" 
                                                    data-name="{{ $label->name }}" 
                                                    data-color="{{ $label->color }}"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editLabelModal">
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm delete-label" 
                                                    data-id="{{ $label->id }}" 
                                                    data-name="{{ $label->name }}"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteLabelModal">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p>You haven't created any labels yet.</p>
                    @endif
                </div>
            </div>
            
            <div class="mt-3">
                <a href="{{ route('notes.index') }}" class="btn btn-secondary">Back to Notes</a>
            </div>
        </div>
    </div>
</div>

<!-- Create Label Modal -->
<div class="modal fade" id="createLabelModal" tabindex="-1" aria-labelledby="createLabelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="createLabelForm" action="{{ route('labels.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createLabelModalLabel">Create New Label</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback" id="name-error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="color" class="form-label">Color</label>
                        <input type="color" class="form-control form-control-color" id="color" name="color" value="#6c757d">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Label</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Label Modal -->
<div class="modal fade" id="editLabelModal" tabindex="-1" aria-labelledby="editLabelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editLabelForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editLabelModalLabel">Edit Label</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="edit-name" name="name" required>
                        <div class="invalid-feedback" id="edit-name-error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-color" class="form-label">Color</label>
                        <input type="color" class="form-control form-control-color" id="edit-color" name="color">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Label</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Label Modal -->
<div class="modal fade" id="deleteLabelModal" tabindex="-1" aria-labelledby="deleteLabelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="deleteLabelForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteLabelModalLabel">Delete Label</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete label "<span id="delete-label-name"></span>"?</p>
                    <p class="text-danger">This will remove the label from all notes, but will not delete the notes.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Label</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle Edit Label Modal
        const editButtons = document.querySelectorAll('.edit-label');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const labelId = this.getAttribute('data-id');
                const labelName = this.getAttribute('data-name');
                const labelColor = this.getAttribute('data-color');
                
                document.getElementById('edit-name').value = labelName;
                document.getElementById('edit-color').value = labelColor;
                
                document.getElementById('editLabelForm').action = `/labels/${labelId}`;
            });
        });
        
        // Handle Delete Label Modal
        const deleteButtons = document.querySelectorAll('.delete-label');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const labelId = this.getAttribute('data-id');
                const labelName = this.getAttribute('data-name');
                
                document.getElementById('delete-label-name').textContent = labelName;
                document.getElementById('deleteLabelForm').action = `/labels/${labelId}`;
            });
        });
    });
</script>
@endpush
@endsection 