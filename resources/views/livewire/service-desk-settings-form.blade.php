<div class="card service-desk-card" x-data="{ mode: @entangle('mode').live }">


      {{-- Current Status --}}
        @if($activeMode)
            <div class="alert alert-info mb-3">
                <strong>Current Status:</strong> 
                <span class="badge bg-primary">{{ strtoupper($activeMode) }}</span> 
                environment is currently active
            </div>
        @endif
    {{-- Header --}}

    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <h4 class="mb-0">ServiceDesk Configuration</h4>

        <div class="d-flex align-items-center gap-2">
            <label class="switch mb-0">
                <input type="checkbox" wire:model.live="is_sync_enabled">
                <span class="slider round"></span>
            </label>

            <span class="fw-bold">
                Enable {{ ucfirst($mode) }} Sync
            </span>

            @if($activeMode === $mode)
                <span class="badge bg-success">ACTIVE</span>
            @endif
        </div>
    </div>


 <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the following:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

    {{-- Environment --}}
    <div class="form-row-horizontal">
        <div class="form-row-label">
            Environment
        </div>
        <div class="form-row-control">
            <select x-model="mode" class="form-control">
                <option value="sandbox">Sandbox</option>
                <option value="production">Production</option>
            </select>

            @if($is_sync_enabled)
                <div class=" text-success">
                    ✔ This environment will be activated when saved
                </div>
            @endif
        </div>
    </div>

    {{-- Base URL --}}
    <div class="form-row-horizontal">
        <div class="form-row-label">
            Base URL <span class="text-danger">*</span>
        </div>
        <div class="form-row-control">
            <input type="text"
                   wire:model.lazy="base_url"
                   class="form-control"
                   required>
        </div>
    </div>

    {{-- Portal ID (Production only) --}}
    <template x-if="mode === 'production'">
        <div class="form-row-horizontal">
            <div class="form-row-label">
                Portal ID
            </div>
            <div class="form-row-control">
                <input type="text"
                       wire:model="portal_id"
                       class="form-control">
                <div class="form-row-help">
                    Optional
                </div>
            </div>
        </div>
    </template>

    {{-- Technician Key --}}
    <div class="form-row-horizontal">
        <div class="form-row-label">
            Technician Key <span class="text-danger">*</span>
        </div>
        <div class="form-row-control">
            <input type="text"
                   wire:model="technician_key"
                   class="form-control"
                   required>
        </div>
    </div>

   
    {{-- Timeout --}}
    <div class="form-row-horizontal">
        <div class="form-row-label">
            Timeout (seconds) <span class="text-danger">*</span>
        </div>
        <div class="form-row-control">
            <input type="number"
                   wire:model="timeout"
                   class="form-control"
                   min="10"
                   required>
        </div>
    </div>

    {{-- Verify SSL --}}
    <div class="form-row-horizontal">
        <div class="form-row-label">
            Verify SSL
        </div>
        <div class="form-row-control d-flex align-items-center">
            <label class="switch mb-0">
                <input type="checkbox" wire:model="verify_ssl">
                <span class="slider round"></span>
            </label>
        </div>
    </div>

    {{-- Save --}}
    <div class="form-row-horizontal mt-4">
        <div class="form-row-label"></div>
        <div class="form-row-control">
            <button wire:click.prevent="save"
                    wire:loading.attr="disabled"
                    class="btn btn-primary">
                Save {{ ucfirst($mode) }} Configuration
            </button>
        </div>
    </div>

</div>

</div>