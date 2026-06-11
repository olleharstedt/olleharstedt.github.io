; Template using SRFI49 notation

; reset printer or linefeed

if not store_receipt
  reset_printer

linefeed 2

if settings.receipt_print_logo
  print_logo
  linefeed

if settings.receipt_print_company_name
  header store.store_name
  linefeed

center store.store_address
