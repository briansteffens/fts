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
			return;
		
		$target = $context->request->node;
		
		// Inserts need the parent node's permissions checked
		if ($context->request->method === "POST" &&
			!isset($context->request->chunk_index))
			$target = $context->request->node_parent;
		
		// GET requires read access
		$permission_required = "r";
		
		// POST/PUT/DELETE all require write access
		if ($context->request->method !== "GET")
			$permission_required = "w";			
		
		// Owner can still read or write node metadata.
		if ($target->user == $this->user &&
			$context->request->content_type === "json" &&
			!isset($context->request->chunk_index) &&
			($context->request->method === "GET" || 
			$context->request->method === "PUT"))
			return;
		
		// Get all the permission components (u/g/o) the user matches.
		if ($target->user == $this->user)
			$checks[] = $target->permissions[0];
		if (in_array($target->group, $this->groups))
			$checks[] = $target->permissions[1];
		$checks[] = $target->permissions[2];
		
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
