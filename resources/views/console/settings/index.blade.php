@extends('console.layout.app')

@section('title', 'Settings')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h4 class="fw-bold mb-1">Settings</h4>
                <p class="text-muted mb-0">Manage your account and application preferences</p>
            </div>
        </div>

        <div class="row">
            {{-- Account --}}
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <a href="{{ route('console.settings.profile') }}" class="card border h-100 text-body text-decoration-none settings-card">
                    <div class="card-body d-flex align-items-start">
                        <div class="avatar avatar-lg me-3 flex-shrink-0">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="icon-base ti tabler-user icon-24px"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="mb-1">Account</h6>
                            <p class="text-muted small mb-0">Profile information and password</p>
                            <span class="text-primary small mt-1 d-inline-flex align-items-center">
                                Manage <i class="icon-base ti tabler-chevron-right ms-1 icon-14px"></i>
                            </span>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Evri Accounts --}}
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <a href="{{ route('console.settings.evri.index') }}" class="card border h-100 text-body text-decoration-none settings-card">
                    <div class="card-body d-flex align-items-start">
                        <div class="avatar avatar-lg me-3 flex-shrink-0">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="icon-base ti tabler-truck-delivery icon-24px"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="mb-1">Evri Accounts</h6>
                            <p class="text-muted small mb-0">Shipping label API accounts</p>
                            <span class="text-primary small mt-1 d-inline-flex align-items-center">
                                Manage <i class="icon-base ti tabler-chevron-right ms-1 icon-14px"></i>
                            </span>
                        </div>
                    </div>
                </a>
            </div>

            {{-- ShipStation Accounts --}}
           @if(config('services.shipstation.enabled')) 
                <div class="col-12 col-md-6 col-lg-4 mb-4">
                    <a href="{{ route('console.settings.shipstation.index') }}" class="card border h-100 text-body text-decoration-none settings-card">
                        <div class="card-body d-flex align-items-start">
                            <div class="avatar avatar-lg me-3 flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="icon-base ti tabler-package icon-24px"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="mb-1">ShipStation Accounts</h6>
                                <p class="text-muted small mb-0">API keys for multiple ShipStation stores</p>
                                <span class="text-primary small mt-1 d-inline-flex align-items-center">
                                    Manage <i class="icon-base ti tabler-chevron-right ms-1 icon-14px"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    </div>

    @push('styles')
    <style>
        .settings-card:hover { border-color: var(--bs-primary) !important; background-color: rgba(var(--bs-primary-rgb), 0.04); }
    </style>
    @endpush
@endsection
