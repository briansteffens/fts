#!/usr/bin/env python3

import sys, os, cmd, json, argparse, readline, requests
from ftsclient import FtsClient

# Parse command line arguments
parser = argparse.ArgumentParser()
	
parser.add_argument("-s", "--server", default=None, help="Server URL", required=True)
parser.add_argument("-u", "--user", default=None, help="Username")
parser.add_argument("-p", "--pass", default=None, help="Password")
	
arglist = parser.parse_args(sys.argv[1:])


class Console(cmd.Cmd):

	def __init__(self, args):
		self.args = args

		self.pwd = "/"

		cmd.Cmd.__init__(self)
		self.prompt = "fts> "
		self.intro = "FTS interactive FTP-style console"

	def do_exit(self, args):
		return -1

	def do_pwd(self, args):
		print(self.pwd)

	def do_ls(self, args):
		r = requests.get(self.args.server + self.pwd + "?json")
		if r.status_code != 200:
			print("Error: " + r.text)
			return
		res = json.loads(r.text)
		for node in res["response"]["node_list"]:
			line = node["permissions"] + " "
			line = line + node["user"] + " " + node["group"] + " "
			line = line + node["name"]
			print(line)
	
	def do_cd(self, args):
		if args[0] == "/":
			self.pwd = args
			return
		
		temp = self.pwd + "/" + args
		parts = [x for x in temp.split("/") if x]
		
		parsed = []
		for part in parts:
			if part == "..":
				parsed = parsed[:-1]
				continue
			parsed.append(part)
		
		target = "/" + "/".join(parsed)
		
		r = requests.get(self.args.server + target + "?json")
		if r.status_code != 200:
			print("Error: " + r.text)
			return
		res = json.loads(r.text)
		if res["response"]["node"]["type"] != "dir":
			print("Error: " + target + " is not a directory.")
			return
		
		self.pwd = target

	
	def do_mkdir(self, args):
		fts = FtsClient(self.args.server)
		fts.get([self.pwd,args])
		

	## Override methods in Cmd object ##
	def preloop(self):
		cmd.Cmd.preloop(self)   ## sets up command completion
		self._hist    = []      ## No history yet
		self._locals  = {}      ## Initialize execution namespace for user
		self._globals = {}

	def postloop(self):
		cmd.Cmd.postloop(self)   ## Clean up command completion
		print("Exiting...")

	def precmd(self, line):
		self._hist += [ line.strip() ]
		return line

	def postcmd(self, stop, line):
		return stop

	def emptyline(self):
		pass
	
if __name__ == "__main__":
	Console(arglist).cmdloop()

