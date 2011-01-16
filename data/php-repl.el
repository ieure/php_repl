;;; php-repl.el --- Interact with PHP

;; Copyright © 2009 Ian Eure

;; Maintainer: Ian Eure <ieure@php.net>
;; Author: Ian Eure
;; Keywords: php repl
;; Created: 2009-03-27
;; Modified: 2009-03-27
;; X-URL:   http://atomized.org

;;; License

;; This file is free software; you can redistribute it and/or
;; modify it under the terms of the GNU General Public License
;; as published by the Free Software Foundation; either version 3
;; of the License, or (at your option) any later version.

;; This file is distributed in the hope that it will be useful,
;; but WITHOUT ANY WARRANTY; without even the implied warranty of
;; MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
;; GNU General Public License for more details.

;; You should have received a copy of the GNU General Public License
;; along with this file; if not, write to the Free Software
;; Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
;; 02110-1301, USA.

;;; Usage

;; Put this file in your Emacs lisp path (eg. site-lisp) and add to
;; your .emacs file:
;;
;;   (require 'php-repl)
;;
;; You can run an inferior PHP by invoking run-php.
;;

(defconst php-repl-version-number "0.8.2"
  "PHP_Repl Mode version number.")

(require 'comint)

(defgroup php-repl nil
  "Major mode for interacting with an inferior PHP interpreter."
  :prefix "ph-repl-"
  :group 'php)

(defcustom php-repl-program
  "php-repl"
  "Path to the PHP interpreter"
  :type '(file))

(defcustom php-repl-program-arguments
  '()
  "Arguments for the PHP program."
  :type '(repeat string))

(defcustom php-use-eval-php-mode nil
  "Whether to enable php-eval-mode for PHP buffers."
  :type 'boolean
  :group 'php)

(defvar inferior-php-buffer nil
  "The buffer of the current inferior PHP processs")

;;;###autoload
(defun run-php (&optional arg)
  "Run an inferior PHP interpreter."
  (interactive "p")
  (let ((buf (cond ((buffer-live-p inferior-php-buffer) inferior-php-buffer)
                   (t (generate-new-buffer "*inferior-php*")))))
    (apply 'make-comint-in-buffer "PHP" buf php-repl-program nil php-repl-program-arguments)
    (setq inferior-php-buffer buf)
    (display-buffer buf t)
    (pop-to-buffer buf t)
    (inferior-php-mode)))

(define-derived-mode inferior-php-mode comint-mode "Inferior PHP")
(defvar inferior-php-mode-abbrev-table
  (make-abbrev-table))
(if (boundp 'php-mode-abbrev-table)
    (derived-mode-merge-abbrev-tables php-mode-abbrev-table
				      inferior-php-mode-abbrev-table))
(derived-mode-set-abbrev-table 'inferior-php-mode)

(defvar eval-php-mode-map
  (let ((map (make-sparse-keymap)))
    (define-key map "\C-c\C-r" 'php-send-region)
    ;; (define-key map "\C-\M-x" 'php-send-defun)
    (define-key map "\C-x\C-e" 'php-send-sexp)
    ;; (define-key map "\C-x\C-:" 'php-send-expression)
    map)
  "Keymap for eval-php-mode.")

(define-minor-mode eval-php-mode
  "Minor mode for evaluating PHP code in an inferior process." nil "eval"
  :keymap eval-php-mode-map
  :group 'php-repl
  :global nil)

(add-hook 'php-mode-hook
          '(lambda ()
             (when php-use-eval-php-mode (eval-php-mode t)))

(defun php-send-region (start end)
  "Send a region to the inferior PHP process."
  (interactive "r")
  (if (not (buffer-live-p inferior-php-buffer))
      (run-php))
  (save-excursion
    (comint-send-region inferior-php-buffer start end)
    (if (not (string-match "\n$" (buffer-substring start end)))
        (comint-send-string sql-buffer "\n"))
    (display-buffer inferior-php-buffer))))

(defun php-eval-sexp ())
(defun php-eval-expression ())
(defun php-eval-buffer ())
(defun php-eval-line ())

(provide 'php-repl)
