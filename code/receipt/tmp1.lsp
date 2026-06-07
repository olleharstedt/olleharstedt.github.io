; Store info
(if settings.receipt_print_company_name
    '(
        (header store.store_name)
        linefeed
    ))
