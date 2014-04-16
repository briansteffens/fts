fts spec (rev 2) notes/ideas
================

##Authentication

*This section is just for testing and likely to change significantly.*

All HTTP requests should accept an authorization token, used to identify a user to the server.
It can be supplied in the following ways:

- in the URI querystring as key 'auth'
- in the password field of HTTP basic authentication

The authorization token is optional: servers that allow anonymous access can ignore missing
tokens.

When the authorization token is invalid, the server should respond with HTTP 401 Unauthorized.

Not currently specified (up to implementation/configuration):

- how the authorization token is obtained
- whether or how often the authorization token expires


##Resource URL basics

FTS URLs all refer to either a directory or a file. Directory links must always end in a 
forward-slash. Example:
```
https://example.com/some_folder/
```

File links do not end in a forward-slash. Example:
```
https://example.com/some_folder/some_file.tar
```


##URL query-string examples

Create a directory owned by you with default permissions (755):
```
PUT https://example.com/some/folder/
```

Create a directory with custom ownership but default permissions (755):
```
PUT https://example.com/some/folder/?user=john&group=users
```

Create a directory with custom ownership and permissions:
```
PUT https://example.com/some/folder/?user=john&group=users&permissions=777
```

View an HTML listing of a directory:
```
GET https://example.com/some/folder/
```

Get a directory's metadata and contents in JSON:
```
GET https://example.com/some/folder/?json
```

Download a file:
```
GET https://example.com/some/folder/some_file.tar
```

Download a file's metadata in JSON:
```
GET https://example.com/some/folder/some_file.tar?json
```

Create and upload a file with file contents in HTTP request body:
```
PUT https://example.com/some/folder/some_file.tar
```

Create a file and start a chunked upload:
```
PUT https://example.com/file.tar?file_size=3823772&chunk_size=382764&file_hash=88jklhuiohowjiojwf08gh39hg93hg893h84g3&chunk_1_hash=48FH#@g
```


##JSON style examples

*'user', 'group', and 'permissions' fields are all optional and default to current user/group and 755.*

JSON describing a directory:
```json
{
	"uri": {*string*},
	"user": {*string*},
	"group": {*string*},
	"permissions": {*string (bitmask)*},
}
```

JSON describing a file:
```json
{
	"uri": {*string*},
	"user": {*string*},
	"group": {*string*},
	"permissions": {*string (bitmask)*},

	"file_size": {*int*},
	"chunk_size": {*int*},
	
	"file_hash": {*string*},
	"chunk_hashes": \[{*string*}\],
}
```


