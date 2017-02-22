<?php
namespace KongHmac;

class KongHmac
{
    public static function generateRequestHeaders($userName, $secret, $url, $data = null, $contentType = null)
    {
        $signatureHeaders = array();
        $base64md5 = "";
        $contentLength = "";

        // Determine request method
        if ($data == null || $contentType == null) {
            $requestMethod = "GET";
        } else {
            $requestMethod = "POST";

            // MD5 digest of the content
            $base64md5 = KongHmac::md5HashBase64($data);

            // Set the content-length header
            $contentLength = strlen($data);

            # Add headers for the signature hash
            $signatureHeaders["content-type"] = $contentType;
            $signatureHeaders["content-md5"] = $base64md5;
            $signatureHeaders["content-length"] = $contentLength;
        }

        // Build the request-line header
        $parsedUrl = parse_url($url);
        $targetUrl = $parsedUrl["path"];
        if (!empty($parsedUrl["query"])) {
            $targetUrl = $targetUrl . "?" . $parsedUrl["query"];
        }
        $requestLine = $requestMethod . " " . $targetUrl . " HTTP/1.1";

        // Set the date header
        $dateHeader = KongHmac::createDateHeader();
        $signatureHeaders["date"] = $dateHeader;

        // Add to headers for the signature hash
        $signatureHeaders["request-line"] = $requestLine;

        // Get the list of headers
        $headers = KongHmac::getHeadersString($signatureHeaders);

        // Build the signature string
        $signatureString = KongHmac::getSignatureString($signatureHeaders);

        // Hash the signature string using the specified algorithm
        $signatureHash = KongHmac::sha1HashBase64($signatureString, $secret);

        // Set the signature hash algorithm
        $algorithm = "hmac-sha1";

        // Format the authorization header
        $authHeaderTemplate = 'hmac username="%s",algorithm="%s",headers="%s",signature="%s"';
        $authHeader = sprintf($authHeaderTemplate, $userName, $algorithm, $headers, $signatureHash);

        // Set the request headers
        if ($requestMethod === "GET") {
            $requestHeaders = array(
                "Host" => $parsedUrl["host"],
                "Authorization" => $authHeader,
                "Date" => $dateHeader
            );
        } else {
            $requestHeaders = array(
                "Host" => $parsedUrl["host"],
                "Authorization" => $authHeader,
                "Date" => $dateHeader,
                "Content-Type" => $contentType,
                "Content-MD5" => $base64md5,
                "Content-Length" => $contentLength
            );
        }

        return $requestHeaders;
    }

    private static function createDateHeader()
    {
        return gmdate("D, d M Y H:i:s", time()) . " GMT";
    }

    private static function getHeadersString($signatureHeaders)
    {
        $headers = "";
        foreach($signatureHeaders as $key => $val)
        {
            if ($headers !== "") {
                $headers .= " ";
            }
            $headers .= $key;
        }
        return $headers;
    }

    private static function getSignatureString($signatureHeaders)
    {
        $sigString = "";
        foreach($signatureHeaders as $key => $val)
        {
            if ($sigString !== "") {
                $sigString .= "\n";
            }
            if (mb_strtolower($key) === "request-line") {
                $sigString .= $val;
            } else {
                $sigString .= mb_strtolower($key) . ": " . $val;
            }
        }
        return $sigString;
    }

    private static function sha1HashBase64($signatureString, $secret)
    {
        $h = hash_hmac('sha1', $signatureString, $secret, $raw_output = true);
        return base64_encode($h);
    }

    private static function md5HashBase64($data)
    {
        $m = md5($data, $raw_output = true);
        return base64_encode($m);
    }
}