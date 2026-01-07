<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\ServiceDeskConfig;
use Illuminate\Support\Facades\Auth;

class ServiceDeskSettingsForm extends Component
{
    public $mode = 'sandbox'; // sandbox | production
    public $portal_id;
    public $base_url;
    public $api_version = 'v3';
    public $technician_key;
    public $dirty = false;
    public $verify_ssl = false;
    public $timeout = 60;
    public $is_sync_enabled = false; // Current mode के लिए
    public $save_button = 'Save';

    protected function rules()
    {
        return [
            'mode' => 'required|in:sandbox,production',
            'base_url' => 'required|url',
            'technician_key' => 'required|string',
            'timeout' => 'required|integer|min:10',
        ];
    }

    public function mount()
    {
        $companyId = Auth::user()->company_id;

        // Load active config first
        $activeConfig = ServiceDeskConfig::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if ($activeConfig) {
            $this->mode = $activeConfig->mode;
        }

        // Load current mode's config
        $config = ServiceDeskConfig::where('company_id', $companyId)
            ->where('mode', $this->mode)
            ->first();

        if ($config) {
            $this->portal_id = $config->portal_id;
            $this->base_url = $config->base_url;
            $this->api_version = $config->api_version;
            $this->technician_key = $config->technician_key;
            $this->verify_ssl = (bool) $config->verify_ssl;
            $this->timeout = $config->timeout;
            $this->is_sync_enabled = (bool) $config->is_sync_enabled;
        }
    }

    public function updatedMode()
    {
        $companyId = auth()->user()->company_id;

        // Load current mode's config
        $config = ServiceDeskConfig::where('company_id', $companyId)
            ->where('mode', $this->mode)
            ->first();

        if ($config) {
            $this->portal_id = $config->portal_id;
            $this->base_url = $config->base_url;
            $this->api_version = $config->api_version;
            $this->technician_key = $config->technician_key;
            
            $this->verify_ssl = (bool) $config->verify_ssl;
            $this->timeout = $config->timeout;
            $this->is_sync_enabled = (bool) $config->is_sync_enabled;
        } else {
            // Reset fields for new mode
            $this->portal_id = null;
            $this->base_url = null;
            $this->technician_key = null;
            $this->verify_ssl = false;
            $this->timeout = 60;
            $this->is_sync_enabled = false; 
        }
    }

    public function save()
    {
        $this->validate();

        $companyId = auth()->user()->company_id;

        if ($this->is_sync_enabled) {
            ServiceDeskConfig::where('company_id', $companyId)
                ->update([
                    'is_sync_enabled' => false,
                    'is_active' => false
                ]);
        }

        // Save current mode's config
        ServiceDeskConfig::updateOrCreate(
            [
                'company_id' => $companyId,
                'mode' => $this->mode,
            ],
            [
                'portal_id' => $this->portal_id,
                'base_url' => $this->base_url,
                'api_version' => $this->api_version ?? 'v3',
                'technician_key' => $this->technician_key,
                'verify_ssl' => $this->verify_ssl,
                'timeout' => $this->timeout,
                'is_sync_enabled' => $this->is_sync_enabled,
                'is_active' => $this->is_sync_enabled, 
                'created_by' => auth()->id(),
            ]
        );

        $this->dispatch('notify',
            type: 'success',
            message: ucfirst($this->mode) . ' ServiceDesk settings saved'
        );
    }

    public function render()
    {
        // Check which mode is currently active
        $companyId = Auth::user()->company_id;
        $activeConfig = ServiceDeskConfig::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();
            
        $activeMode = $activeConfig ? $activeConfig->mode : null;

        return view('livewire.service-desk-settings-form', [
            'activeMode' => $activeMode
        ]);
    }
}