<?php

declare(strict_types=1);

/**
 * Authza Interactive Demo - API Handler
 * 
 * Handles AJAX requests for permission checking
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Authza\Core\AuthzaFactory;
use Demo\DemoData;
use Demo\ExplanationGenerator;

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Initialize Authza using the factory
    $authz = AuthzaFactory::createFromFile(__DIR__ . '/config.php');
    
    // Load demo data
    $data = new DemoData();
    $explainer = new ExplanationGenerator();
    
    // Get request parameters
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check':
            handleCheckPermission($authz, $data, $explainer);
            break;
            
        case 'batch':
            handleBatchCheck($authz, $data);
            break;
            
        case 'matrix':
            handleMatrixGeneration($authz, $data);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
    ]);
}

/**
 * Handle single permission check
 */
function handleCheckPermission($authz, DemoData $data, ExplanationGenerator $explainer): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $resourceKey = $_POST['resource'] ?? '';
    $actionName = $_POST['action_name'] ?? '';
    
    $user = $data->getUser($userId);
    $resources = $data->getAllResources();
    $resource = $resources[$resourceKey] ?? null;
    
    if (!$user || !$resource) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user or resource']);
        return;
    }
    
    $context = $resource->getAttributes();
    $allowed = $authz->can($user, $actionName, $resource, $context);
    $explanation = $explainer->generate($user, $resource, $actionName, $allowed);
    
    echo json_encode([
        'allowed' => $allowed,
        'user' => [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
        ],
        'resource' => [
            'type' => $resource->getResourceType(),
            'id' => $resource->getResourceId(),
            'key' => $resourceKey,
        ],
        'action' => $actionName,
        'explanation' => $explanation,
        'context' => $context,
    ]);
}

/**
 * Handle batch permission check (multiple actions at once)
 */
function handleBatchCheck($authz, DemoData $data): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $resourceKey = $_POST['resource'] ?? '';
    
    $user = $data->getUser($userId);
    $resources = $data->getAllResources();
    $resource = $resources[$resourceKey] ?? null;
    
    if (!$user || !$resource) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user or resource']);
        return;
    }
    
    $actions = $data->getActionsForType($resource->getResourceType());
    $context = $resource->getAttributes();
    $results = [];
    
    foreach ($actions as $action) {
        $results[$action] = $authz->can($user, $action, $resource, $context);
    }
    
    echo json_encode([
        'user' => $user->getName(),
        'resource' => $resourceKey,
        'permissions' => $results,
    ]);
}

/**
 * Handle full permission matrix generation
 */
function handleMatrixGeneration($authz, DemoData $data): void
{
    $resourceType = $_POST['resource_type'] ?? 'invoice';
    
    $users = $data->getUsers();
    $actions = $data->getActionsForType($resourceType);
    
    // Get resources of the specified type
    $resources = match ($resourceType) {
        'invoice' => $data->getInvoices(),
        'document' => $data->getDocuments(),
        'project' => $data->getProjects(),
        default => [],
    };
    
    $matrix = [];
    
    foreach ($users as $userId => $user) {
        $matrix[$userId] = [
            'user' => $user->getName(),
            'roles' => $user->getRoles(),
            'permissions' => [],
        ];
        
        foreach ($resources as $resourceId => $resource) {
            $context = $resource->getAttributes();
            $perms = [];
            
            foreach ($actions as $action) {
                $perms[$action] = $authz->can($user, $action, $resource, $context);
            }
            
            $matrix[$userId]['permissions'][$resourceId] = $perms;
        }
    }
    
    echo json_encode([
        'resourceType' => $resourceType,
        'actions' => $actions,
        'resources' => array_map(fn($r) => [
            'id' => $r->getResourceId(),
            'name' => method_exists($r, 'getTitle') ? $r->getTitle() : $r->getName(),
        ], $resources),
        'matrix' => $matrix,
    ]);
}
