<?php

declare(strict_types=1);

namespace Demo;

use Demo\Models\User;
use Demo\Models\Invoice;
use Demo\Models\Document;
use Demo\Models\Project;

/**
 * Demo data repository - provides sample users and resources
 */
class DemoData
{
    private array $users = [];
    private array $invoices = [];
    private array $documents = [];
    private array $projects = [];

    public function __construct()
    {
        $this->initializeUsers();
        $this->initializeInvoices();
        $this->initializeDocuments();
        $this->initializeProjects();
    }

    private function initializeUsers(): void
    {
        $this->users = [
            1 => new User(1, 'Alice Admin', 'alice@company.com', ['admin'], ['department' => 'IT']),
            2 => new User(2, 'Bob Accountant', 'bob@company.com', ['accountant'], ['department' => 'Finance']),
            3 => new User(3, 'Carol Manager', 'carol@company.com', ['manager', 'employee'], ['department' => 'Sales']),
            4 => new User(4, 'Dave Developer', 'dave@company.com', ['employee'], ['department' => 'Engineering']),
            5 => new User(5, 'Eve Editor', 'eve@company.com', ['editor', 'employee'], ['department' => 'Marketing']),
            6 => new User(6, 'Frank Intern', 'frank@company.com', ['intern'], ['department' => 'HR']),
        ];
    }

    private function initializeInvoices(): void
    {
        $this->invoices = [
            101 => new Invoice(101, 'Website Redesign', 5000.00, 2, 'draft', 'IT'),
            102 => new Invoice(102, 'Office Supplies Q4', 250.00, 4, 'paid', 'General'),
            103 => new Invoice(103, 'Consulting Services', 15000.00, 3, 'pending', 'Sales'),
            104 => new Invoice(104, 'Cloud Hosting', 1200.00, 4, 'draft', 'Engineering'),
        ];
    }

    private function initializeDocuments(): void
    {
        $this->documents = [
            201 => new Document(201, 'Company Handbook', 1, false, 'HR'),
            202 => new Document(202, 'Salary Report 2025', 1, true, 'Finance'),
            203 => new Document(203, 'Project Proposal', 4, false, 'Engineering'),
            204 => new Document(204, 'Strategic Plan', 3, true, 'Executive'),
            205 => new Document(205, 'Marketing Guidelines', 5, false, 'Marketing'),
        ];
    }

    private function initializeProjects(): void
    {
        $this->projects = [
            301 => new Project(301, 'Website Redesign', 3, 'active', [3, 4, 5], 50000.00),
            302 => new Project(302, 'Mobile App', 4, 'active', [4], 25000.00),
            303 => new Project(303, 'Data Migration', 1, 'archived', [1, 2], 150000.00),
            304 => new Project(304, 'Marketing Campaign', 5, 'draft', [5, 3], 10000.00),
        ];
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function getUser(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function getInvoices(): array
    {
        return $this->invoices;
    }

    public function getInvoice(int $id): ?Invoice
    {
        return $this->invoices[$id] ?? null;
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function getDocument(int $id): ?Document
    {
        return $this->documents[$id] ?? null;
    }

    public function getProjects(): array
    {
        return $this->projects;
    }

    public function getProject(int $id): ?Project
    {
        return $this->projects[$id] ?? null;
    }

    /**
     * Get all resources indexed by type:id
     */
    public function getAllResources(): array
    {
        $resources = [];
        
        foreach ($this->invoices as $id => $invoice) {
            $resources["invoice:$id"] = $invoice;
        }
        foreach ($this->documents as $id => $doc) {
            $resources["document:$id"] = $doc;
        }
        foreach ($this->projects as $id => $project) {
            $resources["project:$id"] = $project;
        }
        
        return $resources;
    }

    /**
     * Get actions available for each resource type
     */
    public function getActionsForType(string $type): array
    {
        return match ($type) {
            'invoice' => ['view', 'edit', 'delete', 'approve'],
            'document' => ['view', 'edit', 'delete', 'share', 'download'],
            'project' => ['view', 'edit', 'delete', 'archive', 'add_member', 'remove_member', 'manage_budget'],
            default => ['view', 'edit', 'delete'],
        };
    }
}
