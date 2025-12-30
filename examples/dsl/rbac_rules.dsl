# RBAC Rules
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, view
role:admin, invoice, delete
role:accountant, invoice, view
role:accountant, invoice, edit

# Ownership-based rule
user:42, invoice:123, delete, owner
