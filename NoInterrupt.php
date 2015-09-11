<?php

/********************
 *
 * NoInterrupt - Protect from Exceptions and Fatal errors on a block
 *
 * 2015 by David Buchanan - http://joesvolcano.net/
 *
 * GitHub: https://github.com/unlox775/NoInterrupt-php
 *
 ********************/

class NoInterrupt {
	static function run($function, $throw_warnings = false) {
		$caught_exceptions = array();
		$caught_warnings = array();
		set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($throw_warnings, &$caught_warnings) {
			if ( ini_get('error_reporting') == 0 ) { return; }

			$warning_map = array(
				E_WARNING              => 'Warning',
				E_NOTICE               => 'Notice',
				E_CORE_WARNING         => 'CoreWarning',
				E_COMPILE_WARNING      => 'CoreWarning',
				E_USER_WARNING         => 'UserWarning',
				E_USER_NOTICE          => 'UserNotice',
				E_STRICT               => 'Strict',
				E_DEPRECATED           => 'Deprecated',
				E_USER_DEPRECATED      => 'UserDeprecated',
				);
			$fatal_map = array(
				E_ERROR                => 'Error',
				E_PARSE                => 'Parse',
				E_CORE_ERROR           => 'CoreError',
				E_COMPILE_ERROR        => 'CompileError',
				E_USER_ERROR           => 'UserError',
				E_RECOVERABLE_ERROR    => 'RecoverableError',
				);
			///  FATAL : Throw exception that will be caught below
			if ( isset( $fatal_map[$errno] ) ) {
				$e = new NoInterrupt__PHPErrorHandlerException("PHP ". $fatal_map[$errno] ." error: ". $errstr ." in ". $errfile ." on line ". $errline);
				$e->detail = (object) array(
					'errno'      => $errno,
					'errstr'     => $errstr,
					'errfile'    => $errfile,
					'errline'    => $errline,
					);
				throw $e;
			}
			///  WARNINGS : Add to warnings that can be looked at later.
			else if ( isset( $warning_map[$errno] ) ) {
				$warning = "PHP ". $fatal_map[$errno] ." warning: ". $errstr ." in ". $errfile ." on line ". $errline;
				$caught_warnings[] = $warning;
				if ( $throw_warnings ) {
					$e = new NoInterrupt__PHPWarningHandlerException("PHP ". $fatal_map[$errno] ." error: ". $errstr ." in ". $errfile ." on line ". $errline);
					$e->detail = (object) array(
						'errno'      => $errno,
						'errstr'     => $errstr,
						'errfile'    => $errfile,
						'errline'    => $errline,
						);
					throw $e;
				}
			}
			else { return false; }
			return true;
		});

		// Bring ... it.. on...
		$result = null;
		try {
			$result = $function();
		}
		catch( Exception $e ) {
			$caught_exceptions[ get_class($e) ] = $e;
		}

		restore_error_handler();
		return new NoInterrupt($result, $caught_exceptions, $caught_warnings);
	}

	public $result = null;
	public $caught_exceptions = [];
	public $caught_warnings = [];
	public function __construct($result, $caught_exceptions, $caught_warnings) {
		$this->result = $result;
		$this->caught_exceptions = $caught_exceptions;
		$this->caught_warnings = $caught_warnings;
	}

	public function echoMessages() {
		$echoed_warnings = false;
		$this
		->catch('*', function ($e, $no_interrupt) use(&$echoed_warnings) {
			if ( ! empty( $no_interrupt->caught_warnings ) ) {
				echo "\n\nWarnings:\n". join("\n", $no_interrupt->caught_warnings)."\n\n";
				$echoed_warnings = true;
			}
			echo "\n\nFatal Exception (". get_class($e) ."): ". $e->getMessage()."\n\n";
		})
		->catchWarnings(function ($warnings)      use(&$echoed_warnings) {
			if ( $echoed_warnings ) { return; }
			echo         "Warnings:\n". join("\n",                      $warnings)."\n\n";
		});
	}


	public function __call($func, $params) {
		if ( $func == 'catch' ) { return call_user_func_array(array($this,'__catch'), $params); }
		trigger_error('PHP Fatal error:  Call to undefined method NoInterrupt::{$func}()', E_USER_ERROR);
	}
	public function __catch($e_class, $func) {
		if ( ! empty( $this->caught_exceptions )
			&& (
				$e_class == 'Exception'
				|| $e_class == '*'
				|| isset( $this->caught_exceptions[$e_class] )
				)
			) {
			$func(array_shift($this->caught_exceptions), $this); // Only one?
		}
		return $this;
	}

	public function catchWarnings($func) {
		if ( ! empty( $this->caught_warnings ) ) {
			$func($this->caught_warnings, $this); // Only one?
		}
		return $this;
	}
}
class NoInterrupt__PHPErrorHandlerException extends Exception {}
class NoInterrupt__PHPWarningHandlerException extends Exception {}
