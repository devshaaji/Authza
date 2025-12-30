# RBAC Rules
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, view
role:admin, invoice, delete
role:accountant, invoice, view
role:accountant, invoice, edit

# Ownership-based rule
user:42, invoice:123, delete, owner
# Admin permissions
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, delete
role:admin, invoice, view

# Accountant permissions
role:accountant, invoice, view
role:accountant, invoice, edit

# Sales permissions
role:sales, client, create
role:sales, client, edit
role:sales, client, view
