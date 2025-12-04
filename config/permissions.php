<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Role-Based Access Control (RBAC) Permissions
    |--------------------------------------------------------------------------
    |
    | This file defines the permissions for each role in the system.
    | Each role has access to specific features and actions.
    |
    */

    'roles' => [
        'admin' => [
            'name' => 'Business Owner / Manager',
            'permissions' => [
                // CRM
                'crm.view' => true,
                'crm.create' => true,
                'crm.update' => true,
                'crm.delete' => true,
                
                // Quotes
                'quotes.view' => true,
                'quotes.create' => true,
                'quotes.update' => true,
                'quotes.delete' => true,
                'quotes.send' => true,
                'quotes.approve' => true,
                
                // Jobs
                'jobs.view' => true,
                'jobs.create' => true,
                'jobs.update' => true,
                'jobs.delete' => true,
                'jobs.assign' => true,
                
                // Scheduling
                'scheduling.view' => true,
                'scheduling.create' => true,
                'scheduling.update' => true,
                'scheduling.delete' => true,
                
                // Inventory (view only for admin)
                'inventory.view' => true,
                'inventory.create' => false,
                'inventory.update' => false,
                
                // Invoicing
                'invoices.view' => true,
                'invoices.create' => true,
                'invoices.update' => true,
                'invoices.delete' => true,
                'invoices.send' => true,
                
                // Payments
                'payments.view' => true,
                'payments.create' => true,
                'payments.update' => true,
                
                // Reports
                'reports.view' => true,
                'reports.export' => true,
                
                // Compliance
                'compliance.view' => true,
                'compliance.create' => true,
                'compliance.update' => true,
                'compliance.delete' => true,
                
                // Users
                'users.view' => true,
                'users.create' => true,
                'users.update' => true,
                'users.delete' => true,
                
                // AI Tools
                'ai.scheduling' => true,
                'ai.quoting' => true,
                'ai.inventory' => true,
                
                // Communication
                'communication.view' => true,
                'communication.send' => true,
                
                // Settings
                'settings.view' => true,
                'settings.update' => true,
            ],
        ],

        'manager' => [
            'name' => 'Business Owner / Manager',
            'permissions' => [
                // Same as admin
                'crm.view' => true,
                'crm.create' => true,
                'crm.update' => true,
                'crm.delete' => true,
                'quotes.view' => true,
                'quotes.create' => true,
                'quotes.update' => true,
                'quotes.delete' => true,
                'quotes.send' => true,
                'quotes.approve' => true,
                'jobs.view' => true,
                'jobs.create' => true,
                'jobs.update' => true,
                'jobs.delete' => true,
                'jobs.assign' => true,
                'scheduling.view' => true,
                'scheduling.create' => true,
                'scheduling.update' => true,
                'scheduling.delete' => true,
                'inventory.view' => true,
                'invoices.view' => true,
                'invoices.create' => true,
                'invoices.update' => true,
                'invoices.delete' => true,
                'invoices.send' => true,
                'payments.view' => true,
                'payments.create' => true,
                'reports.view' => true,
                'reports.export' => true,
                'compliance.view' => true,
                'compliance.create' => true,
                'compliance.update' => true,
                'users.view' => true,
                'users.create' => true,
                'users.update' => true,
                'ai.scheduling' => true,
                'ai.quoting' => true,
                'communication.view' => true,
                'communication.send' => true,
                'settings.view' => true,
                'settings.update' => true,
            ],
        ],

        'technician' => [
            'name' => 'Technician / Driver',
            'permissions' => [
                // Jobs (limited)
                'jobs.view' => true,
                'jobs.update' => true,
                'jobs.complete' => true,
                'jobs.view.own' => true, // Only own jobs
                
                // Quotes (create/update on-site)
                'quotes.view' => true,
                'quotes.create' => true,
                'quotes.update' => true,
                
                // No access to
                'crm.view' => false,
                'crm.create' => false,
                'invoices.view' => false,
                'invoices.create' => false,
                'payments.view' => false,
                'reports.view' => false,
                'compliance.view' => false,
                'users.view' => false,
                'scheduling.view' => false,
                'scheduling.create' => false,
                'inventory.view' => false,
                'inventory.create' => false,
                
                // Communication (limited)
                'communication.view' => true,
                'communication.send' => true,
                'communication.view.own' => true, // Only own messages
                
                // AI Tools (limited via app)
                'ai.quoting' => true, // On-site quoting
            ],
        ],

        'dispatcher' => [
            'name' => 'Office Admin',
            'permissions' => [
                // CRM
                'crm.view' => true,
                'crm.create' => true,
                'crm.update' => true,
                'crm.delete' => false,
                
                // Quotes
                'quotes.view' => true,
                'quotes.create' => true,
                'quotes.update' => true,
                'quotes.delete' => false,
                'quotes.send' => true,
                'quotes.approve' => true,
                
                // Jobs (view only)
                'jobs.view' => true,
                'jobs.create' => false,
                'jobs.update' => false,
                'jobs.assign' => false,
                
                // Invoicing
                'invoices.view' => true,
                'invoices.create' => true,
                'invoices.update' => true,
                'invoices.send' => true,
                
                // Payments
                'payments.view' => true,
                'payments.create' => true,
                
                // Communication
                'communication.view' => true,
                'communication.send' => true,
                
                // Reports (limited)
                'reports.view' => true,
                'reports.export' => false,
                
                // Compliance
                'compliance.view' => true,
                'compliance.create' => true,
                'compliance.update' => true,
                
                // No access to
                'users.view' => false,
                'users.create' => false,
                'scheduling.view' => false,
                'scheduling.create' => false,
                'inventory.view' => false,
                'ai.scheduling' => false,
                'settings.view' => false,
                'settings.update' => false,
            ],
        ],

        'warehouse' => [
            'name' => 'Warehouse / Stock Manager',
            'permissions' => [
                // Inventory (full access)
                'inventory.view' => true,
                'inventory.create' => true,
                'inventory.update' => true,
                'inventory.delete' => true,
                'inventory.transfer' => true,
                'inventory.audit' => true,
                
                // Reports (inventory only)
                'reports.view' => true,
                'reports.view.inventory' => true,
                
                // No access to other features
                'crm.view' => false,
                'quotes.view' => false,
                'jobs.view' => false,
                'invoices.view' => false,
                'payments.view' => false,
                'communication.view' => false,
                'compliance.view' => false,
                'users.view' => false,
                'scheduling.view' => false,
                'settings.view' => false,
            ],
        ],

        'client' => [
            'name' => 'Client',
            'permissions' => [
                // View-only access
                'quotes.view' => true,
                'quotes.view.own' => true, // Only own quotes
                'quotes.approve' => true, // Can approve own quotes
                
                'jobs.view' => true,
                'jobs.view.own' => true, // Only own jobs
                
                'invoices.view' => true,
                'invoices.view.own' => true, // Only own invoices
                
                'payments.create' => true, // Can make payments
                
                // No other access
                'crm.view' => false,
                'quotes.create' => false,
                'jobs.create' => false,
                'reports.view' => false,
                'compliance.view' => false,
                'users.view' => false,
                'scheduling.view' => false,
                'inventory.view' => false,
                'communication.view' => false,
                'settings.view' => false,
            ],
        ],
    ],

];

