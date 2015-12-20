<?php

# Description: Simple URL router functionality
# Author: Fredrik Berglund
# Date: 2013-09-16

class Router {
    protected $path = array();
	public $int = array();
	public $str = array();

    public function __construct() {
        $path_raw = $_SERVER['REQUEST_URI'];

		if ('' != $_SERVER['QUERY_STRING']) {
			$hit = mb_strpos($path_raw, '?' . $_SERVER['QUERY_STRING']);

			if (false !== $hit) {
				$path_raw = mb_substr($path_raw, 0, $hit);
			}
		}
        if ('/' != mb_substr($path_raw, -1))
            header("Location: $path_raw/");
        if ('/' != $path_raw)
            $this->path = explode('/', trim($path_raw, '/'));
    }

    public function redirect($path) {
        if ('/' == $path) {
            header("Location: /");
            exit;
        }
        
        $path_raw = '/' . implode('/', $this->path) . '/';
        $path = '/' . trim($path, '/') . '/';

        if ($path_raw != $path) {
            header("Location: $path");
            exit;
        }

        return false;
    }

    public function is_path($path) {
        $path_arr = array();

        if ('/' != $path) {
            $path_arr = explode('/', trim($path, '/'));
        }

        # See if we have any whildcards in $path definition
        foreach ($path_arr as $key => $val) {
            if ('*' == $val) {
                if (0 == $key) {
                    return true;
                }
                array_splice($path_arr, $key);
                foreach ($path_arr as $key => $val) {
                    if ($this->path[$key] != $val) {
                        return false;
                    }
                }
                return true;
            }
        }

        if (count($path_arr) != count($this->path)) {
            return false;
        }

        foreach ($path_arr as $key => $val) {
            if ($this->path[$key] != $val) {
				if ('[int]' == $val) {
					if (!intval($this->path[$key])) {
						return false;
					}
					$this->int[$key] = $this->path[$key]; 
				} else if ('[str]' == $val) {
					$this->str[$key] = $this->path[$key];
				} else {
					return false;
				}
            }
        }

        return true;
    }

    public function get_path() {
        return '/' . implode('/', $this->path) . '/';
    }

    public function host() {
		$prot = 'http://';

		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
			$prot = 'https://';
		}

		return $prot . $_SERVER['HTTP_HOST'];
	}
}
