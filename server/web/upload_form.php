<?php

$username = authenticate_user();

?>

<html>
<body>

<form action="upload.html" method="post" enctype="multipart/form-data">
	<label for="file" />File to upload:</label>
	<input type="file" name="file" id="file" /><br />

	<label for="content_type" />Content type:</label>
    <input type="input" name="content_type" id="content_type"
        value="text/plain" /><br />

	<input type="submit" name="submit" value="Upload" />
</form>

</body>
</html>
