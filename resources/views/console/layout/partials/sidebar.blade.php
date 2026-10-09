<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo">
        <a href="index.html" class="app-brand-link">
            <span class="app-brand-logo demo">
                <span class="text-primary">
                    <svg width="32" height="22" viewBox="0 0 32 22" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z"
                            fill="currentColor" />
                        <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                            d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616" />
                        <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                            d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616" />
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z"
                            fill="currentColor" />
                    </svg>
                </span>
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-3">{{ env('APP_NAME') }}</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none"></i>
        </a>
    </div>

    {{-- 
        @dd( auth()->user()->getRoleNames()['0'],
            auth()->user()->can('view collection'),
            auth()->user()->getAllPermissions()->toArray());  
    --}}

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <li class="menu-item {{ request()->routeIs('console.dashboard') ? 'active' : '' }}">
            <a href="{{ route('console.dashboard') }}" class="menu-link dashboard-menu-link">
                <div>Dashboard</div>
            </a>
        </li>

        <li class="menu-item {{ request()->routeIs('console.liveStats.*') ? 'active' : '' }}">
            <a href="{{ route('console.liveStats.index') }}" class="menu-link">
                <div>Live Stats</div>
            </a>
        </li>

        @can('view orders')
        <li class="menu-item {{ request()->routeIs('console.orders.index') ? 'active' : '' }}">
            <a href="{{ route('console.orders.index') }}" class="menu-link">
                <div>Orders</div>
            </a>
        </li>

        @endcan

        @can('view Tracking')
        <li class="menu-item {{ request()->routeIs('console.tracking.*') ? 'active' : '' }}">
            <a href="{{ route('console.tracking.index') }}" class="menu-link">
                <div>Tracking</div>
            </a>
        </li>
        @endcan

        @can('view batches')
        <li class="menu-item {{ request()->routeIs('console.batchManagement.*') ? 'open active' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <div>Batch Management</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('console.batchManagement.batches.index') ? 'active' : '' }} {{ request()->routeIs('console.batchManagement.completed.process') ? 'active' : '' }}">
                    <a href="{{ route('console.batchManagement.batches.index') }}" class="menu-link">
                        <div>Batches</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('console.batchManagement.completed.index') ? 'active' : '' }}">
                    <a href="{{ route('console.batchManagement.completed.index') }}" class="menu-link">
                        <div>Completed Batches</div>
                    </a>
                </li>
            </ul>
        </li>
        @endcan

        @if(auth()->user()->can('view users') || auth()->user()->can('view roles'))
        <li class="menu-item {{ request()->routeIs('console.userManagement.*') ? 'open active' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <div>User Management</div>
            </a>
            <ul class="menu-sub">
                @can('view users')
                <li class="menu-item {{ request()->routeIs('console.userManagement.users.*') ? 'active' : '' }}">
                    <a href="{{ route('console.userManagement.users.index') }}" class="menu-link">
                        <div>Users</div>
                    </a>
                </li>
                @endcan
                @can('view roles')
                <li class="menu-item {{ request()->routeIs('console.userManagement.roles.*') ? 'active' : '' }}">
                    <a href="{{ route('console.userManagement.roles.index') }}" class="menu-link">
                        <div>Roles</div>
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endif

        @can('view products')
            <li class="menu-item {{ request()->routeIs('console.products.*') ? 'active' : '' }}">
                <a href="{{ route('console.products.index') }}" class="menu-link">
                    <div>Products</div>
                </a>
            </li>
        @endcan
 
        @can('view collection')
            <li class="menu-item {{ request()->routeIs('console.collections.*') ? 'active' : '' }}">
                <a href="{{ route('console.collections.index') }}" class="menu-link">
                    <div>Collections</div>
                </a>
            </li>
        @endcan
      

        @can('view stitchers')
        <li class="menu-item {{ request()->routeIs('console.stitchers.*') ? 'active' : '' }}">
            <a href="{{ route('console.stitchers.index') }}" class="menu-link">
                <div>Stitchers</div>
            </a>
        </li>
        {{-- <li class="menu-item {{ request()->routeIs('console.stitchers.*') ? 'active' : '' }}">
            <a href="{{ route('console.stitchers.index') }}" class="menu-link stitchers-menu-link">
                <div>Stitchers</div>
            </a>
        </li> --}}
        @endcan

        @can('view Returns')
        <li class="menu-item {{ request()->routeIs('console.returns.*') ? 'active' : '' }}">
            <a href="{{ route('console.returns.index') }}" class="menu-link">
                <div>Returns</div>
            </a>
        </li>
        @endcan

        @can('view replacements')
        <li class="menu-item {{ request()->routeIs('console.replacements.*') ? 'active' : '' }}">
            <a href="{{ route('console.replacements.index') }}" class="menu-link">
                <div>Replacements</div>
            </a>
        </li>
        @endcan

        @can('view prestock')
        <li class="menu-item {{ request()->routeIs('console.prestock.*') ? 'active' : '' }}">
            <a href="{{ route('console.prestock.index') }}" class="menu-link">
                <div>Prestock</div>
            </a>
        </li>
        @endcan

        @can('view labels')
        <li class="menu-item {{ request()->routeIs('console.shippingLabels.*') ? 'active' : '' }}">
            <a href="{{ route('console.shippingLabels.index') }}" class="menu-link">
                <div>Shipping Labels</div>
            </a>
        </li>
        @endcan
        @can('view shipmate')
            <li class="menu-item {{ request()->routeIs('console.shipmate.*') ? 'active' : '' }}">
                <a href="{{ route('console.shipmate.index') }}" class="menu-link">
                    <div>Manual Shipping Labels</div>
                </a>
            </li>
        @endcan
        @canany(['view labels', 'view shipmate'])
            <li class="menu-item {{ request()->routeIs('console.scanTracking.*') ? 'active' : '' }}">
                <a href="{{ route('console.scanTracking.index') }}" class="menu-link">
                    <div>Parcels</div>
                </a>
            </li>
        @endcan

        @can('delete orders')
            <li class="menu-item {{ request()->routeIs('console.orders.deleted.*') ? 'active' : '' }}">
                <a href="{{ route('console.orders.deleted.index') }}" class="menu-link">
                    <div>Deleted Orders</div>
                </a>
            </li>
        @endcan

        @can('view reports')
            <li class="menu-item {{ request()->routeIs('console.reports.*') ? 'active' : '' }}">
                <a href="{{ route('console.reports.index') }}" class="menu-link">
                    <div>Reports</div>
                </a>
            </li>
        @endcan

        <li class="menu-item {{ request()->routeIs('console.settings.index') || request()->routeIs('console.settings.evri.*') || request()->routeIs('console.settings.profile') ? 'active' : '' }}">
            <a href="{{ route('console.settings.index') }}" class="menu-link settings-menu-link">
                <div>Settings</div>
            </a>
        </li>
    </ul>
</aside>

<div class="menu-mobile-toggler d-xl-none rounded-1">
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
        <i class="ti tabler-menu icon-base"></i>
        <i class="ti tabler-chevron-right icon-base"></i>
    </a>
</div>
