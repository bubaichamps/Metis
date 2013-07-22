In the event of an error within a Metis function, we will return a numerical code, logically numbered, with a resource type of INT. The following are such error codes and their meaning.

- 0.00 - Success (rarely used, such as with updating and writing)
- 1.xx - Any error beginning with an int of 1 is related to connections.
- 2.xx - Any error beginning with an int of 2 is related to fetching / reading a file. In the event that such fetching / reading is the result of a connection failure, we return a 1.xx code.
- 3.xx - Any error beginning with an int of 3 is related to writing / updating a file. In the event that such fetching / reading is the result of a connection failure, we return a 1.xx code. In the event 
that the error is the result of failing to read a file (necessary for updating with the "a" *appending* flag), we will return a 2.xx or a 1.xx, depending on the scenario.
- 4.xx - Any error beginnign with an int of 4 is relating to the deletion of a file.
- 5.xx - Any error beginning with an int of 4 is related to using Metis' query language / syntax.

---

- 1.01 - Invalid or missing nodeList.
- 1.02 - Node does not exist.
- 1.03 - Failing to get a particular value from a node.
- 1.04 - Failure during connection via FTP.
- 1.05 - Failure when changing the directory via ftp_chdir. This can be a result of the directory not existing.

- 2.01 - Failure to navigate to the preferential navigation of the local node. (*As FTP preferential location changes in establishConnection(), if there is an error it will return #1.04*)
- 2.02 - The node's type is not "local" or "ftp".
- 2.03 - The node's preferential location is empty (string length is 0).
- 2.04 - Files defined for interaction are not strings.
- 2.05 - File does not exist.
- 2.06 - Json Decoding has failed (refer to [PHP documentation](http://php.net/manual/en/function.json-decode.php)). You may want to use json_last_error() to find the issue.

- 3.01 - Json Encoding has failed (refer to [PHP documentation](http://php.net/manual/en/function.json-encode.php)).

- 4.01 - Failure to delete file. The file most likely doesn't exist.