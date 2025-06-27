; (defmacro when (condition body)
  ; `(if ,condition ,body))
(defmacro when (condition body) (if condition body))
(when 1 (message "hello"))
