#!/usr/bin/env python2

import sys, argparse, unittest, requests, json

config = {}
execfile("integration.test.conf", config)

class TestContext(object):

	def __init__(self, conf):
		self.conf = conf

	def url(self, subpath, response_type):
		ret = self.conf["server"] + self.conf["test_path"] + subpath
		if response_type is None or response_type == "":
			return ret
		return ret + "?" + response_type

tc = TestContext(config)

class Tests(unittest.TestCase):

	def reset(self):
		r = requests.delete(self.tc.url("", "json"))
		assert r.status_code == 200 or r.status_code == 404
		
		data = json.dumps({"type": "dir"})
		r = requests.post(self.tc.url("", "json"), data=data)
		assert r.status_code == 200

	def setUp(self):
		global tc
		self.tc = tc
		self.reset()
		
	def tearDown(self):
		pass

# Make sure root account overrides security restrictions
# Test the various permission flags 0-7

class BasicFileTests(Tests):
	
	def test_create_file_basic(self):
		r = requests.post(self.tc.url("file1", ""), "file contents")
		assert r.status_code == 200
		
		r = requests.get(self.tc.url("file1", "json"))
		assert r.status_code == 200
		data = json.loads(r.text)
		assert data["response"]["node"]["name"] == "file1"
		assert data["response"]["node"]["type"] == "file"
		assert data["response"]["node"]["file_size"] == 13
		assert data["response"]["node"]["chunk_size"] == 13
		
		r = requests.get(self.tc.url("file1", ""))
		assert r.status_code == 200
		assert r.text == "file contents"
	
	def test_create_file_metadata(self):
		# greetings!
		h = "2ccac904b966db5c0e3076e127bb12b9b0298dfe3539b91c3c01eb70a7ad8e2c"
		# gree
		h1 = "432e8704c5d054ea55a8a6bcf629ae25cadc099a4c8ba91604a885f631254974"
		# ting
		h2 = "327fc6597e6a5988c76a0d3c6ca06a2fc1484f56969e0755413a8b90ca4b89ff"
		# s!
		h3 = "831cccfec3cac9ecd3318c6bf384ec357188b9f0eb3cd28167b49885903944ab"
		data = {
			"type": "file",
			"file_size": 10,
			"chunk_size": 4,
			"file_hash": h,
			"chunk_hashes": [
				h1, h2, h3,
			],
		}
		# Start a file by posting metadata describing it
		r = requests.post(self.tc.url("file1", "json"), json.dumps(data))
		assert r.status_code == 200
		
		# Get file metadata back from server
		r = requests.get(self.tc.url("file1", "json"))
		assert r.status_code == 200
		data = json.loads(r.text)
		assert data["response"]["node"]["name"] == "file1"
		assert data["response"]["node"]["type"] == "file"
		assert data["response"]["node"]["permissions"] == "755"
		assert data["response"]["node"]["user"] == "anon"
		assert data["response"]["node"]["group"] == "anon"
		assert data["response"]["node"]["file_size"] == 10
		assert data["response"]["node"]["file_hash"] == h
		assert data["response"]["node"]["chunk_size"] == 4
		
		# Attempt to download the file. HTTP 409 because file is incomplete.
		r = requests.get(self.tc.url("file1", ""))
		assert r.status_code == 409
		
		# Upload the first chunk
		url = self.tc.url("file1", "json") + "&chunk="
		r = requests.post(url + "0", "gree")
		assert r.status_code == 200
		
		# Upload second chunk
		r = requests.post(url + "1", "ting")
		assert r.status_code == 200
		
		# Upload third chunk
		r = requests.post(url + "2", "s!")
		assert r.status_code == 200
		
		# Attempt to download completed file
		r = requests.get(self.tc.url("file1", ""))
		assert r.status_code == 200
		assert r.text == "greetings!"
		

class BasicDirTests(Tests):

	def test_404(self):
		r = requests.get(self.tc.url("invalid/path", "json"))
		assert r.status_code == 404

	def test_list_directory(self):
		r = requests.get(self.tc.url("", "json"))
		assert r.status_code == 200
		
		res = json.loads(r.text)["response"]
		assert len(res["node_list"]) == 0
		assert res["node"]["name"] == "testing"
		
		r = requests.post(self.tc.url("dir1", "json"), json.dumps({"type": "dir"}))
		assert r.status_code == 200

		r = requests.post(self.tc.url("dir2", "json"), json.dumps({"type": "dir"}))
		assert r.status_code == 200
		
		r = requests.get(self.tc.url("", "json"))
		assert r.status_code == 200
		
		res = json.loads(r.text)["response"]
		assert len(res["node_list"]) == 2
		names = [res["node_list"][0]["name"], res["node_list"][1]["name"]]
		assert "dir1" in names and "dir2" in names
		assert res["node"]["name"] == "testing"
		

	def test_create_directory(self):
		r = requests.post(self.tc.url("dir1", "json"), json.dumps({"type": "dir"}))
		assert r.status_code == 200
		
		r = requests.get(self.tc.url("dir1", "json"))
		assert r.status_code == 200
		data = json.loads(r.text)
		assert data["response"]["node"]["name"] == "dir1"
		assert data["response"]["node"]["type"] == "dir"
		assert data["response"]["node"]["permissions"] == "755"
		assert data["response"]["node"]["user"] == "anon"
		assert data["response"]["node"]["group"] == "anon"
	
	
	def test_update_directory(self):
		r = requests.post(self.tc.url("dir1", "json"), json.dumps({"type": "dir"}))
		assert r.status_code == 200

		r = requests.get(self.tc.url("dir1", "json"))
		assert r.status_code == 200
		node = json.loads(r.text)["response"]["node"]
		
		node["group"] = "users"
		node["permissions"] = "777"
		
		r = requests.put(self.tc.url("dir1", "json"), json.dumps(node))
		assert r.status_code == 200
		
		r = requests.get(self.tc.url("dir1", "json"))
		assert r.status_code == 200
		node = json.loads(r.text)["response"]["node"]
		
		assert node["group"] == "users"
		assert node["permissions"] == "777"
	
	
	def test_delete_directory(self):
		url = self.tc.url("dir1", "json")
	
		r = requests.post(url, json.dumps({"type": "dir"}))
		assert r.status_code == 200
		
		r = requests.get(url)
		assert r.status_code == 200
		
		r = requests.delete(url)
		assert r.status_code == 200
		
		r = requests.get(url)
		assert r.status_code == 404


if __name__ == "__main__":
	unittest.main()
