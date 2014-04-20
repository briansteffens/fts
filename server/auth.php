<?php

require_once("shared.php");

class FtsAuthException extends FtsServerException {
}

class Auth {

	var $user = NULL;
	var $groups = array();

	public function check_for_credentials($context) {
		$this->user = "anon";
		$this->groups[] = "anon";
		
		if (isset($_SERVER['PHP_AUTH_USER'])) {
			$this->process_login(
				$_SERVER["PHP_AUTH_USER"], 
				$_SERVER['PHP_AUTH_PW']
				);
			return;
		}
	}
	
	function process_login($username, $password) {
		if ($username == "testuser" && $password == "testpass") {
			$this->user = "testuser";
			$this->groups[] = "testgroup";
			return;
		}
	}

	public function check_node_permission($context) {
		if ($this->user === "root")
			return TRUE;
		
		
		$target = $context->request->node;
		
		// Inserts need the parent node's permissions checked
		if ($context->request->method === "POST")
			$target = $context->request->node_parent;
		
		
		// GET requires read access
		$permission_required = "r";
		
		// POST/PUT/DELETE all require write access
		if ($context->request->method !== "GET")
			$permission_required = "w";
		
		
		// Get all the permission components (u/g/o) the user matches.
		$checks = array();
		if ($target->user == $this->user)
			$checks[] = $context->request->node->permissions[0];
		if (in_array($target->group, $this->groups))
			$checks[] = $context->request->node->permissions[1];
		$checks[] = $context->request->node->permissions[2];
		
		foreach ($checks as $check)
			if (in_array($permission_required, $this->get_permissions($check)))
				return;
		
		throw new FtsAuthException(403, "Forbidden");
	}
	
	function get_permissions($octal) {
		$ret = array();
		
		if ($octal >= 4)
			$ret[] = "r";
		
		if ($octal == 2 || $octal == 3 || $octal == 6 || $octal == 7)
			$ret[] = "w";
		
		if ($octal == 1 || $octal == 3 || $octal == 5 || $octal == 7)
			$ret[] = "x";
		
		return $ret;
	}

}

?>
