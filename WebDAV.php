<?php
class WebDAV {

	private $config;

	// $req["Authorization"] = "Basic $hdrvalue".base64_encode( "$username:$password" );
	// $req["Cookie"] = $cookiename . "=" . $cookievalue;
	function __construct( $params ) {
		$this->config = $params + [
			'host'=>'',
			'port'=>80,
			'user'=>'',
			'pass'=>'',
			'proxy_host'=>'',
			'proxy_port'=>''
		];
	}

	/**
	 * issue a HEAD request
	 * @param uri string URI of the document
	 **/
	function head( $uri ) {
		return $this->execute( "HEAD $uri HTTP/1.0" );
	}

	/**
	 * issue a GET http request
	 * @param uri URI (path on server) or full URL of the document
	 **/
	function get( $uri ) {
		return $this->execute( "GET $uri HTTP/1.0" );
	}

	/**
	 * issue a OPTIONS http request
	 * @param uri URI (path on server) or full URL of the document
	 **/
	function options( $uri ) {
		return $this->execute( "OPTIONS $uri HTTP/1.0" );
	}

	/**
	 * issue a POST http request
	 * @param uri string URI of the document
	 * @param query_params array parameters to send in the form "parameter name" => value
	 * @example
	 *   $params = array( "login" => "tiger", "password" => "secret" );
	 *   $http->post( "/login.php", $params );
	 **/
	function post( $uri, $query_params="" ) {
		$requestBody = "";
		if( is_array($query_params) ) {
			$postArray = array();
			foreach( $query_params as $k=>$v ) {
				$postArray[] = urlencode($k) . "=" . urlencode($v);
			}
			$requestBody = implode( "&", $postArray);
		}

		return $this->execute( "POST $uri HTTP/1.0", [
			'Content-Type'=>"application/x-www-form-urlencoded"
		], $requestBody );
	}

	/**
	 * Put
	 * Send a PUT request
	 * PUT is the method to sending a file on the server. it is *not* widely supported
	 * @param uri the location of the file on the server. dont forget the heading "/"
	 * @param filecontent the content of the file. binary content accepted
	 * @return string response status code 201 (Created) if ok
	 * @see RFC2518 "HTTP Extensions for Distributed Authoring WEBDAV"
	 **/
	function put( $uri, $filecontent ) {
		return $this->execute( "PUT $uri HTTP/1.0", [], $filecontent );
	}

	/**
	 * Send a MOVE HTTP-DAV request
	 * Move (rename) a file on the server
	 * @param srcUri the current file location on the server. dont forget the heading "/"
	 * @param destUri the destination location on the server. this is *not* a full URL
	 * @param overwrite boolean - true to overwrite an existing destinationn default if yes
	 * @return string response status code 204 (Unchanged) if ok
	 * @see RFC2518 "HTTP Extensions for Distributed Authoring WEBDAV"
	 **/
	function move( $srcUri, $destUri, $overwrite=true ) {
		return $this->execute( "MOVE $srcUri HTTP/1.0", [
			'Overwrite'=>$overwrite ? "T" : "F",
			'Destination'=>$destUri
		]);
	}

	/**
	 * Send a COPY HTTP-DAV request
	 * Copy a file -allready on the server- into a new location
	 * @param srcUri the current file location on the server. dont forget the heading "/"
	 * @param destUri the destination location on the server. this is *not* a full URL
	 * @param overwrite boolean - true to overwrite an existing destination - overwrite by default
	 * @return string response status code 204 (Unchanged) if ok
	 * @see RFC2518 "HTTP Extensions for Distributed Authoring WEBDAV"
	 **/
	function copy( $srcUri, $destUri, $overwrite=true ) {
		return $this->execute( "COPY $srcUri HTTP/1.0", [
			'Overwrite'=>$overwrite ? "T" : "F",
			'Destination'=>$destUri
		]);
	}

	/**
	 * Send a MKCOL HTTP-DAV request
	 * Create a collection (directory) on the server
	 * @param uri the directory location on the server. dont forget the heading "/"
	 * @return string response status code 201 (Created) if ok
	 * @see RFC2518 "HTTP Extensions for Distributed Authoring WEBDAV"
	 **/
	function mkcol( $uri ) {
		return $this->execute( "MKCOL $uri HTTP/1.0" );
	}

	/**
	 * Delete a file on the server using the "DELETE" HTTP-DAV request
	 * This HTTP method is *not* widely supported
	 * Only partially supports "collection" deletion, as the XML response is not parsed
	 * @param uri the location of the file on the server. dont forget the heading "/"
	 * @return string response status code 204 (Unchanged) if ok
	 * @see RFC2518 "HTTP Extensions for Distributed Authoring WEBDAV"
	 **/
	function delete( $uri ) {
		return $this->execute( "DELETE $uri HTTP/1.0" );
	}

	/**
	 * PropFind
	 * implements the PROPFIND method
	 * PROPFIND retrieves meta informations about a resource on the server
	 * XML reply is not parsed, you'll need to do it
	 * @param uri the location of the file on the server. dont forget the heading "/"
	 * @param scope set the scope of the request.
	 *         O : infos about the node only
	 *         1 : infos for the node and its direct children ( one level)
	 *         Infinity : infos for the node and all its children nodes (recursive)
	 * @return string response status code - 207 (Multi-Status) if OK
	 * @see RFC2518 "HTTP Extensions for Distributed Authoring WEBDAV"
	 **/
	function propfind( $uri, $scope=0 ) {
		return $this->execute( "PROPFIND $uri HTTP/1.0", [
			'Depth'=>$scope
		]);
	}

	/**
	 * Lock - WARNING: EXPERIMENTAL
	 * Lock a ressource on the server. XML reply is not parsed, you'll need to do it
	 * @param $uri URL (relative) of the resource to lock
	 * @param $lockScope -  use "exclusive" for an eclusive lock, "inclusive" for a shared lock
	 * @param $lockType - acces type of the lock : "write"
	 * @param $lockScope -  use "exclusive" for an eclusive lock, "inclusive" for a shared lock
	 * @param $lockOwner - an url representing the owner for this lock
	 * @return server reply code, 200 if ok
	 **/
	function lock( $uri, $lockScope, $lockType, $lockOwner ) {
		$body = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>
<D:lockinfo xmlns:D='DAV:'>
<D:lockscope><D:$lockScope/></D:lockscope>\n<D:locktype><D:$lockType/></D:locktype>
	<D:owner><D:href>$lockOwner</D:href></D:owner>
</D:lockinfo>\n";
		$requestBody = utf8_encode( $body );
		return $this->execute( "LOCK $uri HTTP/1.0", [], $requestBody );
	}

	/**
	 * Unlock - WARNING: EXPERIMENTAL
	 * unlock a ressource on the server
	 * @param $uri URL (relative) of the resource to unlock
	 * @param $lockToken  the lock token given at lock time, eg: opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4
	 * @return server reply code, 204 if ok
	 **/
	function unlock( $uri, $lockToken ) {
		return $this->execute( "UNLOCK $uri HTTP/1.0", [
			"Lock-Token"=>"<$lockToken>"
		]);
	}

	/*********************************************
	 * @scope only protected or private methods below
	 **/

	/**
	  * send a request
	  * data sent are in order
	  * a) the command
	  * b) the request headers if they are defined
	  * c) the request body if defined
	  * @return string the server repsonse status code
	  **/
	function execute( $command, $requestHeaders = [], $requestBody = "" ) {
		$crlf = "\r\n";
		if( !empty($this->config['proxy_host']) ) {
			$host = $this->config['proxy_host'];
			$port = $this->config['proxy_port'];
		} else {
			$host = $this->config['host'];
			$port = $this->config['port'];
		}
		if( $port == "" )  $port = 80;
		$socket = fsockopen( $host, $port );
		if( ! $socket ) {
			return false;
		}

		if( $requestBody != ""  ) {
			$requestHeaders["Content-Length"] = strlen( $requestBody );
		}

		$cmd = $command . $crlf;
		foreach( $requestHeaders as $k => $v ) {
			$cmd .= "$k: $v" . $crlf;
		}
		if( $requestBody != ""  ) {
			$cmd .= $crlf . $requestBody;
		}

		fputs( $socket, $cmd . $crlf );
		$response = $this->processReply($socket);
		fclose( $socket );
		return $response;
	}

	private function processReply($socket) {
		$replyString = trim(fgets( $socket,1024) );
		if( preg_match( "|^HTTP/\S+ (\d+) |i", $replyString, $a )) {
			$reply = $a[1];
		} else {
			$reply = -2; // EBADRESPONSE;
		}
		//	get response headers and body
		$responseHeaders = $this->processHeader($socket);
		$responseBody = $this->processBody($socket);
		return [
			'replay'=>$reply,
			'headers'=>$responseHeaders,
			'body'=>$responseBody
		];
	}

	/**
	  * processHeader() reads header lines from socket until the line equals $lastLine
	  * @scope protected
	  * @return array of headers with header names as keys and header content as values
	  **/
	private function processHeader( $socket, $lastLine = "\r\n" ) {
		$headers = array();
		$finished = false;
		while ( ( ! $finished ) && ( ! feof($socket)) ) {
			$str = fgets( $socket, 1024 );
			$finished = ( $str == $lastLine );
			if ( !$finished ) {
				list( $hdr, $value ) = explode( ": ", $str, 2 );
				// nasty workaround broken multiple same headers (eg. Set-Cookie headers) @FIXME
				if( isset( $headers[$hdr]) )
					$headers[$hdr] .= "; " . trim($value);
				else
					$headers[$hdr] = trim($value);
			}
		}
		return $headers;
	}

	/**
	  * processBody() reads the body from the socket
	  * the body is the "real" content of the reply
	  * @return string body content
	  * @scope private
	  **/
	private function processBody($socket) {
		$failureCount = 0;
		$data = "";
		$counter = 0;
		do {
			$status = socket_get_status( $socket );
			if( $status['eof'] == 1 ) {
				break;
			}
			if( $status['unread_bytes'] > 0 ) {
				$buffer = fread( $socket, $status['unread_bytes'] );
				$counter = 0;
			} else {
				$buffer = fread( $socket, 1024 );
				$failureCount++;
				usleep(2);
			}
			$data .= $buffer;
		} while(  $status['unread_bytes'] > 0 || $counter++ < 10 );
		return $data;
	}

}
