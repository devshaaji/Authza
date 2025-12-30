# Manager can approve invoices in their department
role:manager, invoice, approve, department==finance
role:manager, invoice, approve, department==sales

# Only edit unpaid invoices
role:accountant, invoice, edit, status!=paid
