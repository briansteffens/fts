fts
===

File Transfer Service (prototype)

Still just messing around, not really fit for day to day use.

Some goals:
- HTTP web service style interface for easy automation.
- Receive files through wget or a browser with an HTTP GET.
- Optionally break uploaded files into chunks and allow resumes.
- Upload large files from multiple internet connections at once.
- Automatically perform hash checks to confirm file transfers.


Client installation
===================
The FTS client needs python3 and requests. To download and install:
```bash
git clone https://github.com/briansteffens/fts
cd fts/client
sudo make install
```

To uninstall (from git directory):
```bash
sudo make uninstall
```


Server installation
===================
The development server uses Vagrant and Puppet:
```bash
git clone https://github.com/briansteffens/fts
cd fts
vagrant up
```
After `vagrant up`, the server should be available at localhost:9999.



Curl examples
=================
```bash
# Uploads a file
curl -u <username>:<password> -i -X POST \
     http://localhost:9999/upload_simple?file_name=<filename> \
     -H "Content-Type: <content type>" \
     --data-binary "@<filename>"
```