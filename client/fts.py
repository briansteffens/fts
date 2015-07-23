#!/usr/bin/python3

import sys, os, subprocess, argparse, requests, json, hashlib, math

class UploadContext(object):
	server_url = None			# Base url of server
	local_file_name = None		# Physical path to local file
	remote_file_id = None		# Unique file ID assigned by server
	file_size = None			# Total bytes in file
	chunk_size = None			# Bytes per upload chunk
	remote_file_name = None		# Filename-only (no path) for headers when
								# downloading with a browser
	content_type = None			# Mime type for headers when downloading with a
	                            # browser
	file_hash = None			# Full hash of the file
	total_chunks = None			# Total number of chunks - ceil
	                            # (file_size / chunk_size)
	
	def __init__(self, server_url):
		self.server_url = server_url
	

class Uploader(object):
	context = None
	
	def __init__(self, context):
		self.context = context
		
	def upload_chunk(self, chunk_index):
		buf = None
		with open(self.context.local_file_name, "rb") as f:
			f.seek(chunk_index * self.context.chunk_size)
			buf = f.read(self.context.chunk_size)
		url = self.context.server_url + self.context.remote_file_id + "/" + \
		      str(chunk_index)
		res = requests.post(url, buf)
		return json.loads(res.text)
		

def parse_arguments(arguments=sys.argv[1:]):
	parser = argparse.ArgumentParser()
	
	parser.add_argument("-s", "--server", default=None, help="Server URL",
	                    required=True)
	parser.add_argument("-u", "--username", default=None,
	                    help="Username for uploads")
	parser.add_argument("-p", "--password", default=None,
	                    help="Password for uploads")
	parser.add_argument("-fn", "--filename", default=None,
	                    help="File to upload", required=True)
	parser.add_argument("-cs", "--chunk-size", default=-1,
	                    help="Size in bytes of each chunk")
	parser.add_argument("-ct", "--content-type", default="text/plain",
	                    help="Content-type header value (IE text/plain)")
	parser.add_argument("-r", "--resume", default=None, action='store_true')
	
	return parser.parse_args(arguments)
	

if __name__ == "__main__":
	args = parse_arguments()

	username = args.username
	password = args.password

	context = UploadContext(args.server)
	context.local_file_name = args.filename
	spl = context.local_file_name.split("/")
	context.remote_file_name = spl[len(spl) - 1]
	context.file_size = os.path.getsize(args.filename)
	context.content_type = args.content_type

	# Get hash of the whole file
	print("Calculating file hash..")
	context.file_hash = subprocess.check_output(["sha256sum",
	                                             context.local_file_name]);
	context.file_hash = context.file_hash.decode("utf-8").split(' ')[0]

	# If unspecified, assume one chunk the size of the full file
	if args.chunk_size == -1:
		context.chunk_size = context.file_size
	else:
		context.chunk_size = int(args.chunk_size)
	
	# If this is a resume, get details from the server
	if args.resume:
		resume_request = {
			"file_hash": context.file_hash,
		}
		r = requests.post(context.server_url + "resume",
		                  json.dumps(resume_request))
		resume_response = json.loads(r.text)
		context.chunk_size = resume_response["chunk_size"]
		spl = resume_response["file_id"].split("/")
		context.remote_file_id = spl[len(spl) - 1]
		print("Incomplete upload found at " + resume_response["file_id"])

	context.total_chunks = math.ceil(context.file_size / context.chunk_size)

	# If this is not a resume, build and upload a digest to start the new upload
	if not args.resume:
		# Get a hash of each chunk
		print("Calculating chunk hashes..")
		chunks = []
		with open(context.local_file_name, "rb") as f:
			buf = f.read(context.chunk_size)
			while len(buf) != 0:
				chunks.append(hashlib.sha256(buf).hexdigest().strip())
				buf = f.read(context.chunk_size)

		# Assemble digest
		digest = {
			"file_size": context.file_size,
			"file_hash": context.file_hash,
			"chunk_size": context.chunk_size,
			"chunk_hashes": chunks,
			"file_name": context.remote_file_name,
			"content_type": context.content_type,
		}
		digest_str = json.dumps(digest)

		# Report and confirmation		
		print()
		print("File size: " + str(context.file_size) + " bytes")
		print("Chunks: " + str(len(chunks)) + " * " + str(context.chunk_size) +
		      " bytes")
		print("Digest size: " + str(len(digest_str)) + " bytes")		
		
		c = input("Upload digest? [Y/n] ")
		if c != "" and c != "Y":
			sys.exit()

		# Upload digest
		r = requests.post(context.server_url + "start", digest_str,
		                  auth=(username,password,))
		if r.text is None or r.text == "":
			print("No response from server")
			sys.exit()
		result = json.loads(r.text)
		print("Digest uploaded. Incomplete file at " + result["file_id"])
		print()

		# Get newly generated id from server response		
		spl = result["file_id"].split("/")
		context.remote_file_id = spl[len(spl) - 1]
	
	# Confirm chunk upload
	c = input("Upload chunks? [Y/n] ")
	if c != "" and c != "Y":
		sys.exit()
	print()

	# Upload the requested chunks
	uploader = Uploader(context)
	next_index = 0
	while True:
		res = uploader.upload_chunk(next_index)
		print("[" + str(context.total_chunks - res["chunks_remaining"]) + "/" +
		      str(context.total_chunks) + "] Uploaded chunk " +
		      str(next_index) + ".")
		
		if res["next_chunk_index_hint"] == None:
			break;

		next_index = res["next_chunk_index_hint"]
		
	print()

