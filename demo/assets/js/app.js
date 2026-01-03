/**
 * Authza Demo - Interactive Application
 */

// Test history storage
let testHistory = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeTabs();
    initializeClickableRows();
    updateResourceOptions();
    loadMatrix();
});

/**
 * Initialize tab navigation
 */
function initializeTabs() {
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
        });
    });
}

/**
 * Initialize clickable table rows
 */
function initializeClickableRows() {
    // User rows
    document.querySelectorAll('[data-user-id]').forEach(row => {
        row.addEventListener('click', function() {
            document.getElementById('user-select').value = this.dataset.userId;
        });
    });
    
    // Resource rows
    document.querySelectorAll('[data-resource]').forEach(row => {
        row.addEventListener('click', function() {
            const resourceKey = this.dataset.resource;
            const [type] = resourceKey.split(':');
            
            document.getElementById('resource-type-select').value = type;
            updateResourceOptions();
            document.getElementById('resource-select').value = resourceKey;
            updateActionOptions();
        });
    });
}

/**
 * Update resource dropdown based on selected type
 */
function updateResourceOptions() {
    const type = document.getElementById('resource-type-select').value;
    const select = document.getElementById('resource-select');
    const resources = DEMO_DATA[type + 's']; // invoices, documents, projects
    
    select.innerHTML = '';
    
    for (const [id, resource] of Object.entries(resources)) {
        const option = document.createElement('option');
        option.value = `${type}:${id}`;
        
        // Build label based on resource type
        let label = `#${id}: `;
        if (resource.title) {
            label += resource.title;
        } else if (resource.name) {
            label += resource.name;
        }
        
        // Add status/attributes
        if (resource.status) {
            label += ` (${resource.status})`;
        }
        if (resource.confidential) {
            label += ' 🔒';
        }
        
        option.textContent = label;
        select.appendChild(option);
    }
    
    updateActionOptions();
}

/**
 * Update action dropdown based on selected resource type
 */
function updateActionOptions() {
    const type = document.getElementById('resource-type-select').value;
    const select = document.getElementById('action-select');
    const actions = DEMO_DATA.actions[type];
    
    select.innerHTML = '';
    
    actions.forEach(action => {
        const option = document.createElement('option');
        option.value = action;
        option.textContent = action.charAt(0).toUpperCase() + action.slice(1).replace('_', ' ');
        select.appendChild(option);
    });
}

/**
 * Check a single permission via API
 */
function checkPermission() {
    const userId = document.getElementById('user-select').value;
    const resource = document.getElementById('resource-select').value;
    const action = document.getElementById('action-select').value;
    
    // Show loading
    document.getElementById('loading').style.display = 'block';
    document.getElementById('result-box').style.display = 'none';
    document.getElementById('batch-results').style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'check');
    formData.append('user_id', userId);
    formData.append('resource', resource);
    formData.append('action_name', action);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loading').style.display = 'none';
        
        if (data.error) {
            alert('Error: ' + data.message);
            return;
        }
        
        showResult(data);
        addToHistory(data);
    })
    .catch(error => {
        document.getElementById('loading').style.display = 'none';
        console.error('Error:', error);
        alert('Error checking permission. Check console for details.');
    });
}

/**
 * Check all permissions for selected user/resource
 */
function checkAllPermissions() {
    const userId = document.getElementById('user-select').value;
    const resource = document.getElementById('resource-select').value;
    
    document.getElementById('loading').style.display = 'block';
    document.getElementById('result-box').style.display = 'none';
    document.getElementById('batch-results').style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'batch');
    formData.append('user_id', userId);
    formData.append('resource', resource);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loading').style.display = 'none';
        
        if (data.error) {
            alert('Error: ' + data.message);
            return;
        }
        
        showBatchResults(data);
    })
    .catch(error => {
        document.getElementById('loading').style.display = 'none';
        console.error('Error:', error);
        alert('Error checking permissions. Check console for details.');
    });
}

/**
 * Display single permission result
 */
function showResult(data) {
    const resultBox = document.getElementById('result-box');
    resultBox.style.display = 'block';
    resultBox.className = 'result-box ' + (data.allowed ? 'result-allowed' : 'result-denied');
    
    document.getElementById('result-icon').textContent = data.allowed ? '✅' : '🚫';
    
    const titleEl = document.getElementById('result-title');
    titleEl.textContent = data.allowed ? 'ACCESS GRANTED' : 'ACCESS DENIED';
    titleEl.className = 'result-title ' + (data.allowed ? 'allowed' : 'denied');
    
    document.getElementById('result-subtitle').textContent = 
        `${data.user.name} → ${data.action} → ${data.resource.type} #${data.resource.id}`;
    
    document.getElementById('detail-user').textContent = data.user.name;
    document.getElementById('detail-roles').innerHTML = data.user.roles.map(r => 
        `<span class="role-badge role-${r}">${r}</span>`
    ).join(' ');
    document.getElementById('detail-resource').textContent = 
        `${data.resource.type} #${data.resource.id}`;
    document.getElementById('detail-action').textContent = data.action.toUpperCase();
    document.getElementById('explanation-text').textContent = data.explanation;
    
    // Scroll to result
    resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Display batch permission results
 */
function showBatchResults(data) {
    const container = document.getElementById('batch-results');
    const grid = document.getElementById('batch-grid');
    
    container.style.display = 'block';
    grid.innerHTML = '';
    
    for (const [action, allowed] of Object.entries(data.permissions)) {
        const item = document.createElement('div');
        item.className = 'batch-item ' + (allowed ? 'allowed' : 'denied');
        item.innerHTML = `
            <span>${allowed ? '✓' : '✗'}</span>
            <span>${action.replace('_', ' ')}</span>
        `;
        item.onclick = () => {
            document.getElementById('action-select').value = action;
            checkPermission();
        };
        item.style.cursor = 'pointer';
        grid.appendChild(item);
    }
    
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Load a preset scenario
 */
function loadScenario(userId, resource, action) {
    const [type] = resource.split(':');
    
    document.getElementById('user-select').value = userId;
    document.getElementById('resource-type-select').value = type;
    updateResourceOptions();
    document.getElementById('resource-select').value = resource;
    updateActionOptions();
    document.getElementById('action-select').value = action;
    
    // Scroll to tester and check
    document.querySelector('.tester-card').scrollIntoView({ behavior: 'smooth' });
    setTimeout(checkPermission, 200);
}

/**
 * Clear the result display
 */
function clearResult() {
    document.getElementById('result-box').style.display = 'none';
    document.getElementById('batch-results').style.display = 'none';
}

/**
 * Add test to history
 */
function addToHistory(data) {
    const timestamp = new Date().toLocaleTimeString();
    testHistory.unshift({
        ...data,
        timestamp
    });
    
    // Keep only last 50
    if (testHistory.length > 50) {
        testHistory = testHistory.slice(0, 50);
    }
    
    renderHistory();
}

/**
 * Render test history list
 */
function renderHistory() {
    const container = document.getElementById('history-list');
    
    if (testHistory.length === 0) {
        container.innerHTML = '<p class="empty-state">No tests yet. Use the Permission Tester above to start testing!</p>';
        return;
    }
    
    container.innerHTML = testHistory.map((item, index) => `
        <div class="history-item" onclick="replayHistory(${index})">
            <span class="history-icon">${item.allowed ? '✅' : '🚫'}</span>
            <div class="history-content">
                <div class="history-action">
                    <span class="${item.allowed ? 'allowed' : 'denied'}">${item.action.toUpperCase()}</span>
                    on ${item.resource.type} #${item.resource.id}
                </div>
                <div class="history-meta">by ${item.user.name} (${item.user.roles.join(', ')})</div>
            </div>
            <span class="history-time">${item.timestamp}</span>
        </div>
    `).join('');
}

/**
 * Replay a history item
 */
function replayHistory(index) {
    const item = testHistory[index];
    loadScenario(item.user.id, item.resource.key, item.action);
}

/**
 * Clear test history
 */
function clearHistory() {
    testHistory = [];
    renderHistory();
}

/**
 * Load permission matrix for selected resource type
 */
function loadMatrix() {
    const type = document.getElementById('matrix-type').value;
    const container = document.getElementById('matrix-container');
    
    container.innerHTML = '<p class="loading-text">Loading matrix...</p>';
    
    const formData = new FormData();
    formData.append('action', 'matrix');
    formData.append('resource_type', type);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            container.innerHTML = `<p class="loading-text">Error: ${data.message}</p>`;
            return;
        }
        
        renderMatrix(data);
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<p class="loading-text">Error loading matrix. Check console for details.</p>';
    });
}

/**
 * Render the permission matrix table
 */
function renderMatrix(data) {
    const container = document.getElementById('matrix-container');
    const { resourceType, actions, resources, matrix } = data;
    
    let html = '<table class="matrix-table"><thead><tr><th>User / Action</th>';
    
    // Header: resource + action combinations
    for (const resource of Object.values(resources)) {
        for (const action of actions) {
            html += `<th>${action}<br><small>#${resource.id}</small></th>`;
        }
    }
    html += '</tr></thead><tbody>';
    
    // Rows: each user
    for (const [userId, userData] of Object.entries(matrix)) {
        html += `<tr>
            <td>
                <strong>${userData.user}</strong><br>
                <small>${userData.roles.join(', ')}</small>
            </td>`;
        
        for (const [resourceId, perms] of Object.entries(userData.permissions)) {
            for (const action of actions) {
                const allowed = perms[action];
                html += `<td class="${allowed ? 'allowed' : 'denied'} clickable" 
                    onclick="loadScenario(${userId}, '${resourceType}:${resourceId}', '${action}')">
                    ${allowed ? '✓' : '✗'}
                </td>`;
            }
        }
        
        html += '</tr>';
    }
    
    html += '</tbody></table>';
    container.innerHTML = html;
}
