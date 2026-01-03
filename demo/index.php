<?php

declare(strict_types=1);

/**
 * Authza Interactive Demo - Main Entry Point
 * 
 * A comprehensive demo showcasing Authza's authorization capabilities:
 * - Role-Based Access Control (RBAC)
 * - Attribute-Based Access Control (ABAC)
 * - Ownership-based permissions
 * - Team membership permissions
 * - DSL policy definitions
 * - Condition-based rules
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Authza\Core\AuthzaFactory;
use Demo\DemoData;

// Initialize Authza using the factory pattern
$authz = AuthzaFactory::createFromFile(__DIR__ . '/config.php');

// Load demo data
$data = new DemoData();
$users = $data->getUsers();
$invoices = $data->getInvoices();
$documents = $data->getDocuments();
$projects = $data->getProjects();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authza Interactive Demo - Authorization Engine</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>🔐 Authza Interactive Demo</h1>
            <p class="subtitle">Experience the power of modern PHP authorization</p>
            
            <div class="features">
                <span class="feature">✓ RBAC</span>
                <span class="feature">✓ ABAC</span>
                <span class="feature">✓ Ownership Rules</span>
                <span class="feature">✓ Team Membership</span>
                <span class="feature">✓ DSL Policies</span>
                <span class="feature">✓ PHP Policies</span>
            </div>
        </header>

        <!-- Interactive Permission Tester -->
        <section class="card tester-card">
            <h2><span class="icon">🧪</span> Interactive Permission Tester</h2>
            
            <div class="tester-grid">
                <div class="form-group">
                    <label for="user-select">👤 Select User</label>
                    <select id="user-select">
                        <?php foreach ($users as $id => $user): ?>
                        <option value="<?= $id ?>">
                            <?= htmlspecialchars($user->getName()) ?> (<?= implode(', ', $user->getRoles()) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="resource-type-select">📁 Resource Type</label>
                    <select id="resource-type-select" onchange="updateResourceOptions()">
                        <option value="invoice">Invoices</option>
                        <option value="document">Documents</option>
                        <option value="project">Projects</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="resource-select">📦 Select Resource</label>
                    <select id="resource-select" onchange="updateActionOptions()">
                        <!-- Populated by JavaScript -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="action-select">⚡ Select Action</label>
                    <select id="action-select">
                        <!-- Populated by JavaScript -->
                    </select>
                </div>
            </div>
            
            <div class="button-group">
                <button class="btn btn-primary" onclick="checkPermission()">
                    🔍 Check Permission
                </button>
                <button class="btn btn-secondary" onclick="checkAllPermissions()">
                    📋 Check All Actions
                </button>
                <button class="btn btn-outline" onclick="clearResult()">
                    Clear
                </button>
            </div>
            
            <!-- Preset Scenarios -->
            <div class="scenarios">
                <span class="scenario-label">🎯 Try these scenarios:</span>
                <div class="scenario-buttons">
                    <button class="scenario-btn" onclick="loadScenario(1, 'invoice:101', 'delete')">
                        Admin deletes invoice
                    </button>
                    <button class="scenario-btn" onclick="loadScenario(2, 'invoice:102', 'edit')">
                        Accountant edits paid invoice
                    </button>
                    <button class="scenario-btn" onclick="loadScenario(4, 'document:202', 'view')">
                        Employee views confidential doc
                    </button>
                    <button class="scenario-btn" onclick="loadScenario(4, 'project:301', 'edit')">
                        Member edits project
                    </button>
                    <button class="scenario-btn" onclick="loadScenario(6, 'project:301', 'view')">
                        Intern accesses project
                    </button>
                    <button class="scenario-btn" onclick="loadScenario(3, 'project:303', 'edit')">
                        Edit archived project
                    </button>
                    <button class="scenario-btn" onclick="loadScenario(3, 'project:303', 'manage_budget')">
                        Manage large budget
                    </button>
                </div>
            </div>
            
            <!-- Loading Indicator -->
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <span>Checking permission...</span>
            </div>
            
            <!-- Result Display -->
            <div class="result-box" id="result-box">
                <div class="result-header">
                    <span class="result-icon" id="result-icon"></span>
                    <div>
                        <div class="result-title" id="result-title"></div>
                        <div class="result-subtitle" id="result-subtitle"></div>
                    </div>
                </div>
                
                <div class="result-details">
                    <div class="detail-item">
                        <div class="detail-label">User</div>
                        <div class="detail-value" id="detail-user"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Roles</div>
                        <div class="detail-value" id="detail-roles"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Resource</div>
                        <div class="detail-value" id="detail-resource"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Action</div>
                        <div class="detail-value" id="detail-action"></div>
                    </div>
                </div>
                
                <div class="explanation-box">
                    <div class="explanation-title">💡 Why this decision?</div>
                    <pre class="explanation-text" id="explanation-text"></pre>
                </div>
            </div>
            
            <!-- Batch Results -->
            <div class="batch-results" id="batch-results">
                <h3>All Permissions for Selected User & Resource</h3>
                <div class="batch-grid" id="batch-grid"></div>
            </div>
        </section>

        <!-- Tabs Navigation -->
        <nav class="tabs">
            <button class="tab active" data-tab="data">📊 Data Overview</button>
            <button class="tab" data-tab="matrix">🔢 Permission Matrix</button>
            <button class="tab" data-tab="code">💻 Code Examples</button>
            <button class="tab" data-tab="history">📜 Test History</button>
        </nav>

        <!-- Data Overview Tab -->
        <section id="tab-data" class="tab-content active">
            <div class="grid">
                <!-- Users Card -->
                <div class="card">
                    <h2><span class="icon">👥</span> Users (Subjects)</h2>
                    <table class="data-table">
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Roles</th><th>Dept</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $id => $user): ?>
                            <tr class="clickable-row" data-user-id="<?= $id ?>">
                                <td><?= htmlspecialchars($user->getName()) ?></td>
                                <td class="email"><?= htmlspecialchars($user->getEmail()) ?></td>
                                <td>
                                    <?php foreach ($user->getRoles() as $role): ?>
                                        <span class="role-badge role-<?= $role ?>"><?= $role ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= $user->getAttributes()['department'] ?? '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Invoices Card -->
                <div class="card">
                    <h2><span class="icon">📄</span> Invoices</h2>
                    <table class="data-table">
                        <thead>
                            <tr><th>Title</th><th>Amount</th><th>Status</th><th>Owner</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $id => $invoice): ?>
                            <tr class="clickable-row" data-resource="invoice:<?= $id ?>">
                                <td><?= htmlspecialchars($invoice->getTitle()) ?></td>
                                <td>$<?= number_format($invoice->getAmount(), 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $invoice->getStatus() ?>">
                                        <?= ucfirst($invoice->getStatus()) ?>
                                    </span>
                                </td>
                                <td><?= $users[$invoice->getOwnerId()]?->getName() ?? 'Unknown' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Documents Card -->
                <div class="card">
                    <h2><span class="icon">📁</span> Documents</h2>
                    <table class="data-table">
                        <thead>
                            <tr><th>Title</th><th>Owner</th><th>Dept</th><th>Access</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $id => $doc): ?>
                            <tr class="clickable-row" data-resource="document:<?= $id ?>">
                                <td><?= htmlspecialchars($doc->getTitle()) ?></td>
                                <td><?= $users[$doc->getOwnerId()]?->getName() ?? 'Unknown' ?></td>
                                <td><?= $doc->getDepartment() ?></td>
                                <td class="<?= $doc->isConfidential() ? 'confidential' : '' ?>">
                                    <?= $doc->isConfidential() ? '🔒 Confidential' : '📖 Public' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Projects Card -->
                <div class="card">
                    <h2><span class="icon">📋</span> Projects</h2>
                    <table class="data-table">
                        <thead>
                            <tr><th>Name</th><th>Owner</th><th>Status</th><th>Members</th><th>Budget</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $id => $project): ?>
                            <tr class="clickable-row" data-resource="project:<?= $id ?>">
                                <td><?= htmlspecialchars($project->getName()) ?></td>
                                <td><?= $users[$project->getOwnerId()]?->getName() ?? 'Unknown' ?></td>
                                <td>
                                    <span class="status-badge status-<?= $project->getStatus() ?>">
                                        <?= ucfirst($project->getStatus()) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php foreach ($project->getMemberIds() as $memberId): ?>
                                        <span class="member-badge" title="<?= $users[$memberId]?->getName() ?>">
                                            <?= substr($users[$memberId]?->getName() ?? '?', 0, 1) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td>$<?= number_format($project->getBudget() ?? 0, 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Permission Matrix Tab -->
        <section id="tab-matrix" class="tab-content">
            <div class="matrix-controls">
                <label>View matrix for:</label>
                <select id="matrix-type" onchange="loadMatrix()">
                    <option value="invoice">Invoices</option>
                    <option value="document">Documents</option>
                    <option value="project">Projects</option>
                </select>
                <button class="btn btn-sm" onclick="loadMatrix()">🔄 Refresh</button>
            </div>
            
            <div class="card">
                <div class="matrix-container" id="matrix-container">
                    <p class="loading-text">Select a resource type and click refresh to load the permission matrix...</p>
                </div>
            </div>
            
            <div class="matrix-legend">
                <span class="legend-item"><span class="allowed">✓</span> Allowed</span>
                <span class="legend-item"><span class="denied">✗</span> Denied</span>
                <span class="legend-item">Click any cell to test that permission</span>
            </div>
        </section>

        <!-- Code Examples Tab -->
        <section id="tab-code" class="tab-content">
            <div class="grid">
                <div class="card">
                    <h2><span class="icon">🏭</span> Factory Pattern Setup</h2>
                    <pre class="code-block"><code>&lt;?php
use Authza\Core\AuthzaFactory;

// Create from config file
$authz = AuthzaFactory::createFromFile('config.php');

// Or from array
$authz = AuthzaFactory::create([
    'policies' => [
        'register' => [
            'invoice' => InvoicePolicy::class,
            'document' => DocumentPolicy::class,
        ],
    ],
    'dsl' => [
        'files' => ['rules/policies.json'],
    ],
]);</code></pre>
                </div>

                <div class="card">
                    <h2><span class="icon">✅</span> Check Permissions</h2>
                    <pre class="code-block"><code>&lt;?php
// Simple check - returns boolean
if ($authz->can($user, 'edit', $invoice)) {
    // User can edit this invoice
}

// With context (for ABAC rules)
$context = ['status' => $invoice->getStatus()];
$allowed = $authz->can($user, 'edit', $invoice, $context);

// Throws exception if denied
$authz->authorize($user, 'delete', $document);
// AuthorizationException if not allowed</code></pre>
                </div>

                <div class="card">
                    <h2><span class="icon">📝</span> DSL Policy Rules</h2>
                    <pre class="code-block"><code>// policies.json
[
  {
    "subject": "role:admin",
    "resource": "invoice",
    "action": "*",
    "effect": "allow"
  },
  {
    "subject": "role:accountant",
    "resource": "invoice",
    "action": "edit",
    "condition": "status!=paid",
    "effect": "allow"
  },
  {
    "subject": "role:intern",
    "resource": "project",
    "action": "*",
    "effect": "deny"
  }
]</code></pre>
                </div>

                <div class="card">
                    <h2><span class="icon">🔧</span> Custom Policy Class</h2>
                    <pre class="code-block"><code>&lt;?php
class ProjectPolicy implements PolicyInterface
{
    public function can(
        SubjectInterface $subject,
        string $action,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        $isOwner = $subject->getId() === $resource->getOwnerId();
        $isMember = in_array($subject->getId(), 
            $resource->getAttributes()['memberIds'] ?? []);
        
        return match ($action) {
            'view' => $isOwner || $isMember,
            'edit' => $isOwner || $isMember,
            'delete' => $isOwner,
            default => false,
        };
    }
}</code></pre>
                </div>
            </div>
        </section>

        <!-- History Tab -->
        <section id="tab-history" class="tab-content">
            <div class="card">
                <div class="history-header">
                    <h2><span class="icon">📜</span> Test History</h2>
                    <button class="btn btn-sm btn-outline" onclick="clearHistory()">Clear History</button>
                </div>
                <div id="history-list">
                    <p class="empty-state">No tests yet. Use the Permission Tester above to start testing!</p>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <p>Powered by <strong>Authza</strong> - Modern PHP Authorization Engine</p>
            <p class="footer-links">
                <a href="https://github.com/authza/authza">GitHub</a> •
                <a href="../README.md">Documentation</a>
            </p>
        </footer>
    </div>

    <!-- Pass PHP data to JavaScript -->
    <script>
        const DEMO_DATA = {
            users: <?= json_encode(array_map(fn($u) => $u->toArray(), $users)) ?>,
            invoices: <?= json_encode(array_map(fn($i) => $i->toArray(), $invoices)) ?>,
            documents: <?= json_encode(array_map(fn($d) => $d->toArray(), $documents)) ?>,
            projects: <?= json_encode(array_map(fn($p) => $p->toArray(), $projects)) ?>,
            actions: {
                invoice: <?= json_encode($data->getActionsForType('invoice')) ?>,
                document: <?= json_encode($data->getActionsForType('document')) ?>,
                project: <?= json_encode($data->getActionsForType('project')) ?>
            }
        };
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
