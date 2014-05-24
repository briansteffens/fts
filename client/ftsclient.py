#!/usr/bin/env python3

import requests, json

class FtsClient(object):

	def __init__(self, server_url, user=None, pwd=None):
		self.server_url = server_url
		self.auth = None
		if user != None or pwd != None:
			self.auth = (user, pwd)
	
	def reduce_path(self, path):
		if isinstance(path, str):
			path = [path]
		
		temp = "/".join(path)
		parts = [x for x in temp.split("/") if x]
		
		parsed = []
		for part in parts:
			if part == "..":
				parsed = parsed[:-1]
				continue
			parsed.append(part)
		
		return "/" + "/".join(parsed)
	
	def make_uri(self, path, response_type = "json"):
		url = self.server_url
		if url[-1] == "/":
			url = url[:-1]
		return url + self.reduce_path(path) + "?" + response_type
	
	def get(self, path):
		r = requests.get(self.make_uri(path), auth=self.auth)
		if r.status_code != 200:
			raise Exception(r.status_code)
		return json.loads(r.text)["response"]
	
	def post(self, path, node):
		data = json.dumps(node)
		r = requests.post(self.make_uri(path), data=data, auth=self.auth)
		if r.status_code != 200:
			raise Exception(r.status_code)
		return json.loads(r.text)["response"]
	
	def put(self, path, node):
		data = json.dumps(node)
		r = requests.put(self.make_uri(path), data=data, auth=self.auth)
		if r.status_code != 200:
			raise Exception(r.status_code)
		return json.loads(r.text)["response"]

	def delete(self, path):
		r = requests.delete(self.make_uri(path), auth=self.auth)
		if r.status_code != 200:
			raise Exception(r.status_code)
		return json.loads(r.text)["response"]

if __name__ == "__main__":
	fts = FtsClient("http://fts.b.cp/")
	print(fts.get("/greetings"))
	#print(fts.post("/greetings/haha", {"type": "dir"}))
	#print(fts.put("/greetings/haha", {"user": "brian"}))
	#print(fts.delete("/greetings/haha"))






