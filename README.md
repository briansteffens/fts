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
git clone https://github.com/Tiltar/fts
cd fts/client
sudo make install
```

To uninstall (from git directory):
```bash
sudo make uninstall
```
