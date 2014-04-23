#!/usr/bin/env python2

import sys, argparse, unittest, requests, json

config = {}
execfile("integration.test.conf", config)

class TestContext(object):

	def __init__(self, conf):
		self.conf = conf
		self.auth = (conf["username"], conf["password"])

	def url(self, subpath, response_type):
		ret = self.conf["server"] + self.conf["test_path"] + subpath
		if response_type is None or response_type == "":
			return ret
		return ret + "?" + response_type

tc = TestContext(config)

class Tests(unittest.TestCase):

	def reset(self):
		r = requests.delete(self.tc.url("", "meta"))
		assert r.status_code == 200 or r.status_code == 404
		
		data = json.dumps({
			"type": "dir",
			"permissions": "777",
		})
		r = requests.post(self.tc.url("", "meta"), data=data)
		assert r.status_code == 200

	def setUp(self):
		global tc
		self.tc = tc
		self.reset()
		
	def tearDown(self):
		pass
	
	def msg(self, message, r=None):
		ret = "\n" 
		
		if r is not None:
			ret += "HTTP " + str(r.status_code) + "\n"
			
		ret += message + "\n"
		
		if r is not None:
			ret += r.text
			
		return ret
	
	def assert_p(self, actual, expected, failmsg, r=None):
		assert actual == expected, self.msg(
			"Actual [" + str(actual) + "], " +
			"expected [" + str(expected) + "]\n" + \
			failmsg, r)


class PermissionTests(Tests):			

	def combined_file_tests(self, permissions, expect, user=None, group=None):
		url = self.tc.url("file1", "")
		urljson = self.tc.url("file1", "meta")
	
		r = requests.post(url, "content", auth=self.tc.auth)
		self.assert_p(r.status_code, 200, "Failed to upload file.")
		
		node = {
			"type": "file",
			"user": self.tc.conf["username"],
			"group": self.tc.conf["group"],
			"permissions": permissions,
		}
		if user is not None:
			node["user"] = user
		if group is not None:
			node["group"] = group
		r = requests.put(urljson, json.dumps(node), auth=self.tc.auth)
		self.assert_p(r.status_code, 200, "Failed to set permissions.")
		
		# can user read file?
		r = requests.get(url, auth=self.tc.auth)
		self.assert_p(r.status_code, expect["read"],
			"Unexpected response while reading file contents.")
		
		# can user read file chunk?
		r = requests.get(urljson + "&chunk=0", auth=self.tc.auth)
		self.assert_p(r.status_code, expect["read_chunk"],
			"Unexpected response while reading file chunk.")
		
		# can user write file chunk?
		r = requests.post(urljson + "&chunk=0", "content", auth=self.tc.auth)
		self.assert_p(r.status_code, expect["write_chunk"],
			"Unexpected response while writing file chunk.")
		
		# can user read file metadata?
		r = requests.get(urljson, auth=self.tc.auth)
		self.assert_p(r.status_code, expect["read_metadata"],
			"Unexpected response while reading file metadata.")
		
		# can user write file metadata?
		r = requests.put(urljson, json.dumps(node), auth=self.tc.auth)
		self.assert_p(r.status_code, expect["write_metadata"],
			"Unexpected response while writing file metadata.")
			
		# can user delete file?
		r = requests.delete(urljson, auth=self.tc.auth)
		self.assert_p(r.status_code, expect["delete_file"],
			"Unexpected response while deleting file.")
	
	
	def combined_dir_tests(self, permissions, expect, user=None, group=None):
		url = self.tc.url("dir1", "")
		urljson = self.tc.url("dir1", "meta")
		
		node = {
			"type": "dir",
			"user": self.tc.conf["username"],
			"group": self.tc.conf["group"],
			"permissions": permissions,
		}
		if user is not None:
			node["user"] = user
		if group is not None:
			node["group"] = group
		r = requests.post(urljson, json.dumps(node), auth=self.tc.auth)
		self.assert_p(r.status_code, 200, "Failed to create directory.",r=r)
		
		
		
	
	def test_x(self):
		self.combined_dir_tests("000", {
			"list": 403,
			"write_metadata": 200,
			"delete": 403,
		})
	

class FileUserPermissionTests(PermissionTests):
	# none
	def test_file_user_0_none(self):
		self.combined_file_tests("000", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 403,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 403,
		})
	# execute
	def test_file_user_1_x(self):
		self.combined_file_tests("100", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 403,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 403,
		})
	# write
	def test_file_user_2_w(self):
		self.combined_file_tests("200", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 200,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 200,
		})
	# write and execute
	def test_file_user_3_wx(self):
		self.combined_file_tests("300", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 200,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 200,
		})
	# read
	def test_file_user_4_r(self):
		self.combined_file_tests("400", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 403,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 403,
		})
	# read and execute
	def test_file_user_5_rx(self):
		self.combined_file_tests("500", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 403,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 403,
		})
	# read and write
	def test_file_user_6_rw(self):
		self.combined_file_tests("600", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 200,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 200,
		})
	# read, write, and execute
	def test_file_user_7_rwx(self):
		self.combined_file_tests("700", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 200,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 200,
		})
		
		

class FileGroupPermissionTests(PermissionTests):

	# none
	def test_file_group_0_none(self):
		self.combined_file_tests("000", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 403,
			"read_metadata": 403,
			"write_metadata": 403,
			"delete_file": 403,
		}, user="someother")
	# execute
	def test_file_group_1_x(self):
		self.combined_file_tests("010", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 403,
			"read_metadata": 403,
			"write_metadata": 403,
			"delete_file": 403,
		}, user="someother")
	# write
	def test_file_group_2_w(self):
		self.combined_file_tests("020", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 200,
			"read_metadata": 403,
			"write_metadata": 200,
			"delete_file": 200,
		}, user="someother")
	# write and execute
	def test_file_group_3_wx(self):
		self.combined_file_tests("030", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 200,
			"read_metadata": 403,
			"write_metadata": 200,
			"delete_file": 200,
		}, user="someother")
	# read
	def test_file_group_4_r(self):
		self.combined_file_tests("040", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 403,
			"read_metadata": 200,
			"write_metadata": 403,
			"delete_file": 403,
		}, user="someother")
	# read and execute
	def test_file_group_5_rx(self):
		self.combined_file_tests("050", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 403,
			"read_metadata": 200,
			"write_metadata": 403,
			"delete_file": 403,
		}, user="someother")
	# read and write
	def test_file_group_6_rw(self):
		self.combined_file_tests("060", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 200,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 200,
		}, user="someother")
	# read, write, and execute
	def test_file_group_7_rwx(self):
		self.combined_file_tests("070", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 200,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 200,
		}, user="someother")


class FileOtherPermissionTests(PermissionTests):

	# none
	def test_file_other_0_none(self):
		self.combined_file_tests("000", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 403,
			"read_metadata": 403,
			"write_metadata": 403,
			"delete_file": 403,
		}, user="someother", group="someother")
	# execute
	def test_file_other_1_x(self):
		self.combined_file_tests("001", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 403,
			"read_metadata": 403,
			"write_metadata": 403,
			"delete_file": 403,
		}, user="someother", group="someother")
	# write
	def test_file_other_2_w(self):
		self.combined_file_tests("002", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 200,
			"read_metadata": 403,
			"write_metadata": 200,
			"delete_file": 200,
		}, user="someother", group="someother")
	# write and execute
	def test_file_other_3_wx(self):
		self.combined_file_tests("003", {
			"read": 403,
			"read_chunk": 403,
			"write_chunk": 200,
			"read_metadata": 403,
			"write_metadata": 200,
			"delete_file": 200,
		}, user="someother", group="someother")
	# read
	def test_file_other_4_r(self):
		self.combined_file_tests("004", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 403,
			"read_metadata": 200,
			"write_metadata": 403,
			"delete_file": 403,
		}, user="someother", group="someother")
	# read and execute
	def test_file_other_5_rx(self):
		self.combined_file_tests("005", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 403,
			"read_metadata": 200,
			"write_metadata": 403,
			"delete_file": 403,
		}, user="someother", group="someother")
	# read and write
	def test_file_other_6_rw(self):
		self.combined_file_tests("006", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 200,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 200,
		}, user="someother", group="someother")
	# read, write, and execute
	def test_file_other_7_rwx(self):
		self.combined_file_tests("007", {
			"read": 200,
			"read_chunk": 200,
			"write_chunk": 200,
			"read_metadata": 200,
			"write_metadata": 200,
			"delete_file": 200,
		}, user="someother", group="someother")




class BasicFileTests(Tests):
	
	def test_create_file_basic(self):
		r = requests.post(self.tc.url("file1", ""), "file contents")
		assert r.status_code == 200
		
		r = requests.get(self.tc.url("file1", "meta"))
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
		r = requests.post(self.tc.url("file1", "meta"), json.dumps(data))
		assert r.status_code == 200
		
		# Get file metadata back from server
		r = requests.get(self.tc.url("file1", "meta"))
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
		url = self.tc.url("file1", "") + "&chunk="
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

	def test_list_directory(self):
		r = requests.get(self.tc.url("", ""))
		assert r.status_code == 200
		
		res = json.loads(r.text)["response"]
		assert len(res["node_list"]) == 0
		assert res["node"]["name"] == "testing"
		
		r = requests.post(self.tc.url("dir1", ""), json.dumps({"type": "dir"}))
		assert r.status_code == 200

		r = requests.post(self.tc.url("dir2", ""), json.dumps({"type": "dir"}))
		assert r.status_code == 200
		
		r = requests.get(self.tc.url("", "meta"))
		assert r.status_code == 200
		
		res = json.loads(r.text)["response"]
		assert len(res["node_list"]) == 2
		names = [res["node_list"][0]["name"], res["node_list"][1]["name"]]
		assert "dir1" in names and "dir2" in names
		assert res["node"]["name"] == "testing"
		

	def test_create_directory(self):
		r = requests.post(self.tc.url("dir1", "meta"), json.dumps({"type": "dir"}))
		assert r.status_code == 200
		
		r = requests.get(self.tc.url("dir1", "meta"))
		assert r.status_code == 200
		data = json.loads(r.text)
		assert data["response"]["node"]["name"] == "dir1"
		assert data["response"]["node"]["type"] == "dir"
		assert data["response"]["node"]["permissions"] == "755"
		assert data["response"]["node"]["user"] == "anon"
		assert data["response"]["node"]["group"] == "anon"



class BasicNodeTests(Tests):

	def test_404(self):
		r = requests.get(self.tc.url("invalid/path", ""))
		assert r.status_code == 404

	
	def test_update_node(self):
		r = requests.post(self.tc.url("dir1", ""), json.dumps({"type": "dir"}))
		assert r.status_code == 200

		r = requests.get(self.tc.url("dir1", "meta"))
		assert r.status_code == 200
		node = json.loads(r.text)["response"]["node"]
		
		node["group"] = "users"
		node["permissions"] = "777"
		
		r = requests.put(self.tc.url("dir1", "meta"), json.dumps(node))
		assert r.status_code == 200
		
		r = requests.get(self.tc.url("dir1", "meta"))
		assert r.status_code == 200
		node = json.loads(r.text)["response"]["node"]
		
		assert node["group"] == "users"
		assert node["permissions"] == "777"
	
	
	def test_delete_node(self):
		url = self.tc.url("dir1", "")
	
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
