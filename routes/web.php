<?php

$routes = [
    '/' => [
        'controller' => 'DashboardController',
        'method'     => 'index',
        'permission' => 'dashboard.view'
    ],

    '/admin/dashboard' => [
        'controller' => 'AdminDashboardController',
        'method'     => 'index',
        'permission' => 'report.view'
    ],

    '/portal' => [
        'controller' => 'PortalController',
        'method'     => 'index'
    ],

    // --- ENERJİ MODÜLÜ ---
    '/energy' => [
        'controller' => 'EnergyDashboardController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy-dashboard' => [
        'controller' => 'EnergyDashboardController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy/consumption' => [
        'controller' => 'EnergyConsumptionController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy/ges' => [
        'controller' => 'EnergyGesController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy/production' => [
        'controller' => 'EnergyProductionController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy/cost' => [
        'controller' => 'EnergyCostController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy-cost' => [
        'controller' => 'EnergyCostController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy/alerts' => [
        'controller' => 'EnergyAlertController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy-alerts' => [
        'controller' => 'EnergyAlertController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy/alerts/ack' => [
        'controller' => 'EnergyAlertController',
        'method'     => 'acknowledge',
        'permission' => 'energy.manage'
    ],

    '/energy/alerts/resolve' => [
        'controller' => 'EnergyAlertController',
        'method'     => 'resolve',
        'permission' => 'energy.manage'
    ],

    '/energy/meters' => [
        'controller' => 'EnergyMeterController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy/reports' => [
        'controller' => 'EnergyReportController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/energy-reports' => [
        'controller' => 'EnergyReportController',
        'method'     => 'index',
        'permission' => 'energy.view'
    ],

    '/dashboard' => [
        'controller' => 'DashboardController',
        'method'     => 'index',
        'permission' => 'dashboard.view'
    ],

    // --- DENETİM İZİ (AUDIT) ---
    '/audit-logs' => [
        'controller' => 'AuditController',
        'method'     => 'index',
        'permission' => 'audit.view'
    ],

    // --- MALZEME YÖNETİMİ ---
    '/materials' => [
        'controller' => 'MaterialController',
        'method'     => 'index',
        'permission' => 'material.view'
    ],

    '/materials/show' => [
        'controller' => 'MaterialController',
        'method'     => 'show',
        'permission' => 'material.view'
    ],

    '/materials/create' => [
        'controller' => 'MaterialController',
        'method'     => 'create',
        'permission' => 'material.create'
    ],

    '/materials/edit' => [
        'controller' => 'MaterialController',
        'method'     => 'edit',
        'permission' => 'material.update'
    ],

    '/materials/delete' => [
        'controller' => 'MaterialController',
        'method'     => 'delete',
        'permission' => 'material.delete'
    ],

    // --- DEPO & LOKASYON ---
    '/warehouses' => [
        'controller' => 'WarehouseController',
        'method'     => 'index',
        'permission' => 'warehouse.view'
    ],

    '/warehouses/create' => [
        'controller' => 'WarehouseController',
        'method'     => 'create',
        'permission' => 'warehouse.manage'
    ],

    '/warehouses/edit' => [
        'controller' => 'WarehouseController',
        'method'     => 'edit',
        'permission' => 'warehouse.manage'
    ],

    '/warehouses/delete' => [
        'controller' => 'WarehouseController',
        'method'     => 'delete',
        'permission' => 'warehouse.manage'
    ],

    '/locations' => [
        'controller' => 'LocationController',
        'method'     => 'index',
        'permission' => 'warehouse.view'
    ],

    '/locations/create' => [
        'controller' => 'LocationController',
        'method'     => 'create',
        'permission' => 'warehouse.manage'
    ],

    '/locations/edit' => [
        'controller' => 'LocationController',
        'method'     => 'edit',
        'permission' => 'warehouse.manage'
    ],

    '/locations/delete' => [
        'controller' => 'LocationController',
        'method'     => 'delete',
        'permission' => 'warehouse.manage'
    ],

    // --- STOK HAREKETLERİ ---
    '/stock-movements' => [
        'controller' => 'StockMovementController',
        'method'     => 'index',
        'permission' => 'stock.view'
    ],

    '/stock/in' => [
        'controller' => 'StockController',
        'method'     => 'in',
        'permission' => 'stock.in'
    ],

    '/stock/out' => [
        'controller' => 'StockController',
        'method'     => 'out',
        'permission' => 'stock.out'
    ],

    '/stock/transfer' => [
        'controller' => 'StockController',
        'method'     => 'transfer',
        'permission' => 'stock.transfer'
    ],

    '/stock/return' => [
        'controller' => 'StockController',
        'method'     => 'return',
        'permission' => 'stock.return'
    ],

    '/stock/adjustment' => [
        'controller' => 'StockController',
        'method'     => 'adjustment',
        'permission' => 'stock.adjustment'
    ],

    '/stock/scrap' => [
        'controller' => 'StockController',
        'method'     => 'scrap',
        'permission' => 'stock.scrap'
    ],

    // --- TEDARİKÇİLER ---
    '/suppliers' => [
        'controller' => 'SupplierController',
        'method'     => 'index',
        'permission' => 'supplier.view'
    ],

    '/suppliers/create' => [
        'controller' => 'SupplierController',
        'method'     => 'create',
        'permission' => 'supplier.manage'
    ],

    '/suppliers/edit' => [
        'controller' => 'SupplierController',
        'method'     => 'edit',
        'permission' => 'supplier.manage'
    ],

    '/suppliers/delete' => [
        'controller' => 'SupplierController',
        'method'     => 'delete',
        'permission' => 'supplier.manage'
    ],

    // --- KULLANICI, ÇALIŞAN & ROL YÖNETİMİ ---
    '/employees/dashboard' => [
        'controller' => 'EmployeeDashboardController',
        'method'     => 'index',
        'permission' => 'employee.view'
    ],

    '/employees' => [
        'controller' => 'EmployeeController',
        'method'     => 'index',
        'permission' => 'employee.view'
    ],

    '/employees/show' => [
        'controller' => 'EmployeeController',
        'method'     => 'show',
        'permission' => 'employee.view'
    ],

    '/employees/create' => [
        'controller' => 'EmployeeController',
        'method'     => 'create',
        'permission' => 'employee.manage'
    ],

    '/employees/edit' => [
        'controller' => 'EmployeeController',
        'method'     => 'edit',
        'permission' => 'employee.manage'
    ],

    '/employees/shifts/assign' => [
        'controller' => 'EmployeeController',
        'method'     => 'assignShift',
        'permission' => 'employee.manage'
    ],

    '/employees/shifts/delete' => [
        'controller' => 'EmployeeController',
        'method'     => 'deleteShift',
        'permission' => 'employee.manage'
    ],

    '/users' => [
        'controller' => 'UserController',
        'method'     => 'index',
        'permission' => 'user.view'
    ],

    '/users/create' => [
        'controller' => 'UserController',
        'method'     => 'create',
        'permission' => 'user.manage'
    ],

    '/users/edit' => [
        'controller' => 'UserController',
        'method'     => 'edit',
        'permission' => 'user.manage'
    ],

    '/users/delete' => [
        'controller' => 'UserController',
        'method'     => 'delete',
        'permission' => 'user.manage'
    ],

    '/role-permissions' => [
        'controller' => 'RolePermissionController',
        'method'     => 'index',
        'permission' => 'role.view'
    ],

    '/role-permissions/update' => [
        'controller' => 'RolePermissionController',
        'method'     => 'update',
        'permission' => 'role.manage'
    ],

    '/role-permissions/reset' => [
        'controller' => 'RolePermissionController',
        'method'     => 'reset',
        'permission' => 'role.manage'
    ],

    // --- DENETİM İZİ (AUDIT) & API TOKEN YÖNETİMİ ---
    '/audit-logs' => [
        'controller' => 'AuditController',
        'method'     => 'index',
        'permission' => 'audit.view'
    ],

    '/api-tokens' => [
        'controller' => 'ApiTokenController',
        'method'     => 'index',
        'permission' => 'api.manage'
    ],

    '/api-tokens/create' => [
        'controller' => 'ApiTokenController',
        'method'     => 'create',
        'permission' => 'api.manage'
    ],

    '/api-tokens/revoke' => [
        'controller' => 'ApiTokenController',
        'method'     => 'revoke',
        'permission' => 'api.manage'
    ],

    // --- RAPORLAR ---
    '/reports' => [
        'controller' => 'ReportController',
        'method'     => 'index',
        'permission' => 'report.view'
    ],

    '/reports/export' => [
        'controller' => 'ReportController',
        'method'     => 'export',
        'permission' => 'report.export'
    ],

    // --- ENVANTER TAKİBİ ---
    '/inventory' => [
        'controller' => 'InventoryController',
        'method'     => 'index',
        'permission' => 'inventory.view'
    ],

    '/inventory/suggestions' => [
        'controller' => 'InventoryController',
        'method'     => 'suggestions',
        'permission' => 'inventory.view'
    ],

    '/inventory/create' => [
        'controller' => 'InventoryController',
        'method'     => 'create',
        'permission' => 'inventory.create'
    ],

    '/inventory/show' => [
        'controller' => 'InventoryController',
        'method'     => 'show',
        'permission' => 'inventory.view'
    ],

    '/inventory/edit' => [
        'controller' => 'InventoryController',
        'method'     => 'edit',
        'permission' => 'inventory.update'
    ],

    '/inventory/assign' => [
        'controller' => 'InventoryController',
        'method'     => 'assign',
        'permission' => 'inventory.update'
    ],

    '/inventory/transfer' => [
        'controller' => 'InventoryController',
        'method'     => 'transfer',
        'permission' => 'inventory.update'
    ],

    '/inventory/return' => [
        'controller' => 'InventoryController',
        'method'     => 'returnAsset',
        'permission' => 'inventory.update'
    ],

    '/inventory/export' => [
        'controller' => 'InventoryController',
        'method'     => 'export',
        'permission' => 'inventory.export'
    ],

    // --- REÇETELER & ÜRETİM ---
    '/recipes' => [
        'controller' => 'RecipeController',
        'method'     => 'index',
        'permission' => 'production.view'
    ],

    '/recipes/create' => [
        'controller' => 'RecipeController',
        'method'     => 'create',
        'permission' => 'recipe.manage'
    ],

    '/recipes/edit' => [
        'controller' => 'RecipeController',
        'method'     => 'edit',
        'permission' => 'recipe.manage'
    ],

    '/recipes/toggle' => [
        'controller' => 'RecipeController',
        'method'     => 'toggle',
        'permission' => 'recipe.manage'
    ],

    '/recipes/delete' => [
        'controller' => 'RecipeController',
        'method'     => 'delete',
        'permission' => 'recipe.manage'
    ],

    '/production' => [
        'controller' => 'ProductionController',
        'method'     => 'index',
        'permission' => 'production.view'
    ],

    '/production/create' => [
        'controller' => 'ProductionController',
        'method'     => 'create',
        'permission' => 'production.execute'
    ],

    '/production/preview-stock' => [
        'controller' => 'ProductionController',
        'method'     => 'previewStock',
        'permission' => 'production.view'
    ],

    '/production/store' => [
        'controller' => 'ProductionController',
        'method'     => 'store',
        'permission' => 'production.execute'
    ],

    '/production/show' => [
        'controller' => 'ProductionController',
        'method'     => 'show',
        'permission' => 'production.view'
    ],

    // --- MES MODÜLÜ ---
    '/mes' => [
        'controller' => 'MesController',
        'method'     => 'index',
        'permission' => 'production.view'
    ],

    '/mes/work-orders/create' => [
        'controller' => 'MesController',
        'method'     => 'createWorkOrder',
        'permission' => 'mes.create'
    ],

    '/mes/work-orders/store' => [
        'controller' => 'MesController',
        'method'     => 'storeWorkOrder',
        'permission' => 'mes.create'
    ],

    '/mes/work-orders/show' => [
        'controller' => 'MesController',
        'method'     => 'showWorkOrder',
        'permission' => 'production.view'
    ],

    '/mes/work-orders/status' => [
        'controller' => 'MesController',
        'method'     => 'updateWorkOrderStatus',
        'permission' => 'mes.create'
    ],

    '/mes/simulator' => [
        'controller' => 'MesController',
        'method'     => 'simulator',
        'permission' => 'mes.simulate'
    ],

    '/mes/simulator/start' => [
        'controller' => 'MesController',
        'method'     => 'startSimulation',
        'permission' => 'mes.simulate'
    ],

    '/mes/simulator/pause' => [
        'controller' => 'MesController',
        'method'     => 'pauseSimulation',
        'permission' => 'mes.simulate'
    ],

    '/mes/simulator/tick' => [
        'controller' => 'MesController',
        'method'     => 'tickSimulation',
        'permission' => 'mes.simulate'
    ],

    '/mes/simulator/status' => [
        'controller' => 'MesController',
        'method'     => 'getSimulationStatus',
        'permission' => 'production.view'
    ],

    '/mes/simulator/produce' => [
        'controller' => 'MesController',
        'method'     => 'simulateProductionEvent',
        'permission' => 'mes.simulate'
    ],

    '/mes/lines/status' => [
        'controller' => 'MesController',
        'method'     => 'updateLineStatus',
        'permission' => 'production.execute'
    ],

    // --- RESTful API ENDPOINTS (Token & Session Authentication) ---
    '/api/mes/events' => [
        'controller' => 'MesController',
        'method'     => 'apiIngest',
        'permission' => 'production.execute'
    ],

    '/api/mes/status' => [
        'controller' => 'MesController',
        'method'     => 'getSimulationStatus',
        'permission' => 'production.view'
    ],

    '/api/mes/lines' => [
        'controller' => 'MesController',
        'method'     => 'apiProductionLines',
        'permission' => 'production.view'
    ],

    '/api/mes/work-order/consumption' => [
        'controller' => 'MesController',
        'method'     => 'apiWorkOrderConsumption',
        'permission' => 'production.view'
    ],

    '/api/mes/event/consumption' => [
        'controller' => 'MesController',
        'method'     => 'apiEventConsumption',
        'permission' => 'production.view'
    ],

    '/api/mes/work-order/traceability' => [
        'controller' => 'MesController',
        'method'     => 'apiWorkOrderTraceability',
        'permission' => 'production.view'
    ],

    '/api/mes/work-order/cost' => [
        'controller' => 'MesController',
        'method'     => 'apiWorkOrderCost',
        'permission' => 'production.view'
    ],

    '/api/mes/recipe/cost' => [
        'controller' => 'MesController',
        'method'     => 'apiRecipeCost',
        'permission' => 'production.view'
    ],

    '/api/mes/event/cost' => [
        'controller' => 'MesController',
        'method'     => 'apiEventCost',
        'permission' => 'production.view'
    ],

    '/api/materials/live-stock' => [
        'controller' => 'MaterialController',
        'method'     => 'liveStock',
        'permission' => 'material.view'
    ],

    // --- OEE & HAT PERFORMANSI MODÜLÜ ---
    '/oee' => [
        'controller' => 'OeeController',
        'method'     => 'index',
        'permission' => 'oee.view'
    ],

    '/oee/api/live' => [
        'controller' => 'OeeController',
        'method'     => 'live',
        'permission' => 'oee.view'
    ],

    '/oee/downtime/start' => [
        'controller' => 'OeeController',
        'method'     => 'startDowntime',
        'permission' => 'oee.view'
    ],

    '/oee/downtime/end' => [
        'controller' => 'OeeController',
        'method'     => 'endDowntime',
        'permission' => 'oee.view'
    ],

    '/oee/snapshot' => [
        'controller' => 'OeeController',
        'method'     => 'createSnapshot',
        'permission' => 'production.execute'
    ],

    // --- CANLI ANDON & FABRİKA VİTRİNİ ---
    '/andon' => [
        'controller' => 'AndonController',
        'method'     => 'index',
        'permission' => 'production.view'
    ],

    '/andon/api/live' => [
        'controller' => 'AndonController',
        'method'     => 'live',
        'permission' => 'production.view'
    ],

    // --- BAKIM YÖNETİMİ & TPM (STAGE 16) ---
    '/maintenance' => [
        'controller' => 'MaintenanceController',
        'method'     => 'index',
        'permission' => 'maintenance.view'
    ],

    '/maintenance/asset' => [
        'controller' => 'MaintenanceController',
        'method'     => 'assetShow',
        'permission' => 'maintenance.view'
    ],

    '/maintenance/work-order' => [
        'controller' => 'MaintenanceController',
        'method'     => 'show',
        'permission' => 'maintenance.view'
    ],

    '/maintenance/create' => [
        'controller' => 'MaintenanceController',
        'method'     => 'create',
        'permission' => 'maintenance.create'
    ],

    '/maintenance/assign' => [
        'controller' => 'MaintenanceController',
        'method'     => 'assign',
        'permission' => 'maintenance.execute'
    ],

    '/maintenance/start' => [
        'controller' => 'MaintenanceController',
        'method'     => 'startWork',
        'permission' => 'maintenance.execute'
    ],

    '/maintenance/spare-part/consume' => [
        'controller' => 'MaintenanceController',
        'method'     => 'consumeSparePart',
        'permission' => 'maintenance.execute'
    ],

    '/maintenance/complete' => [
        'controller' => 'MaintenanceController',
        'method'     => 'complete',
        'permission' => 'maintenance.execute'
    ],

    '/maintenance/verify' => [
        'controller' => 'MaintenanceController',
        'method'     => 'verify',
        'permission' => 'maintenance.execute'
    ],

    '/api/maintenance/live' => [
        'controller' => 'MaintenanceController',
        'method'     => 'liveApi',
        'permission' => 'maintenance.view'
    ],

    // --- BİTMİŞ ÜRÜN & KALİTE KONTROL ---
    '/finished-goods' => [
        'controller' => 'FinishedGoodsController',
        'method'     => 'index',
        'permission' => 'quality.view'
    ],

    '/finished-goods/live' => [
        'controller' => 'FinishedGoodsController',
        'method'     => 'live',
        'permission' => 'quality.view'
    ],

    '/finished-goods/show' => [
        'controller' => 'FinishedGoodsController',
        'method'     => 'show',
        'permission' => 'quality.view'
    ],

    '/finished-goods/serial' => [
        'controller' => 'FinishedGoodsController',
        'method'     => 'showBySerial',
        'permission' => 'quality.view'
    ],

    '/finished-goods/quality-control/save' => [
        'controller' => 'FinishedGoodsController',
        'method'     => 'saveQualityControl',
        'permission' => 'quality.inspect'
    ],

    // --- DEPO VE SEVKİYAT ---
    '/shipments' => [
        'controller' => 'ShipmentController',
        'method'     => 'index',
        'permission' => 'shipment.view'
    ],

    '/shipments/create' => [
        'controller' => 'ShipmentController',
        'method'     => 'create',
        'permission' => 'shipment.create'
    ],

    '/shipments/show' => [
        'controller' => 'ShipmentController',
        'method'     => 'show',
        'permission' => 'shipment.view'
    ],

    '/shipments/add-panel' => [
        'controller' => 'ShipmentController',
        'method'     => 'addPanel',
        'permission' => 'shipment.create'
    ],

    '/shipments/remove-panel' => [
        'controller' => 'ShipmentController',
        'method'     => 'removePanel',
        'permission' => 'shipment.create'
    ],

    '/shipments/complete' => [
        'controller' => 'ShipmentController',
        'method'     => 'complete',
        'permission' => 'shipment.complete'
    ],

    '/shipments/cancel' => [
        'controller' => 'ShipmentController',
        'method'     => 'cancel',
        'permission' => 'shipment.create'
    ],

    // --- SATIN ALMA TALEPLERİ ---
    '/purchase-requests' => [
        'controller' => 'PurchaseRequestController',
        'method'     => 'index',
        'permission' => 'purchase.view'
    ],

    '/purchase-requests/show' => [
        'controller' => 'PurchaseRequestController',
        'method'     => 'show',
        'permission' => 'purchase.view'
    ],

    '/purchase-requests/create' => [
        'controller' => 'PurchaseRequestController',
        'method'     => 'create',
        'permission' => 'purchase.request'
    ],

    '/purchase-requests/edit' => [
        'controller' => 'PurchaseRequestController',
        'method'     => 'edit',
        'permission' => 'purchase.request'
    ],

    '/purchase-requests/submit' => [
        'controller' => 'PurchaseRequestController',
        'method'     => 'submit',
        'permission' => 'purchase.request'
    ],

    '/purchase-requests/approve' => [
        'controller' => 'PurchaseRequestController',
        'method'     => 'approve',
        'permission' => 'purchase.approve'
    ],

    '/purchase-requests/reject' => [
        'controller' => 'PurchaseRequestController',
        'method'     => 'reject',
        'permission' => 'purchase.approve'
    ],

    '/purchase-requests/cancel' => [
        'controller' => 'PurchaseRequestController',
        'method'     => 'cancel',
        'permission' => 'purchase.request'
    ],

    // --- SATIN ALMA SİPARİŞLERİ (PURCHASE ORDERS) ---
    '/purchase-orders' => [
        'controller' => 'PurchaseOrderController',
        'method'     => 'index',
        'permission' => 'purchase.order.view'
    ],

    '/purchase-orders/show' => [
        'controller' => 'PurchaseOrderController',
        'method'     => 'show',
        'permission' => 'purchase.order.view'
    ],

    '/purchase-orders/create' => [
        'controller' => 'PurchaseOrderController',
        'method'     => 'create',
        'permission' => 'purchase.order.create'
    ],

    '/purchase-orders/edit' => [
        'controller' => 'PurchaseOrderController',
        'method'     => 'edit',
        'permission' => 'purchase.order.update'
    ],

    '/purchase-orders/send' => [
        'controller' => 'PurchaseOrderController',
        'method'     => 'send',
        'permission' => 'purchase.order.send'
    ],

    '/purchase-orders/confirm' => [
        'controller' => 'PurchaseOrderController',
        'method'     => 'confirm',
        'permission' => 'purchase.order.send'
    ],

    '/purchase-orders/cancel' => [
        'controller' => 'PurchaseOrderController',
        'method'     => 'cancel',
        'permission' => 'purchase.order.cancel'
    ],

    '/purchase-orders/from-request' => [
        'controller' => 'PurchaseOrderController',
        'method'     => 'createFromRequest',
        'permission' => 'purchase.order.create'
    ],

    // --- SATIN ALMA MAL KABUL (GOODS RECEIPTS) ---
    '/purchase-receipts' => [
        'controller' => 'PurchaseReceiptController',
        'method'     => 'index',
        'permission' => 'purchase.order.view'
    ],

    '/purchase-receipts/show' => [
        'controller' => 'PurchaseReceiptController',
        'method'     => 'show',
        'permission' => 'purchase.order.view'
    ],

    '/purchase-receipts/create' => [
        'controller' => 'PurchaseReceiptController',
        'method'     => 'create',
        'permission' => 'stock.in'
    ],

    // --- AUTHENTICATION ---
    '/login' => [
        'controller' => 'AuthController',
        'method'     => 'login'
    ],

    '/logout' => [
        'controller' => 'AuthController',
        'method'     => 'logout'
    ],
];