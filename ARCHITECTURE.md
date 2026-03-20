# Architecture: laminas-diactoros

## Purpose
A PHP PSR-7 and PSR-17 implementation. Provides immutable HTTP message value objects: Request, Response, Uri, Stream, UploadedFile, and ServerRequest — plus PSR-17 factories for each.

## Directory Structure
```
src/
  Request.php / Response.php / Uri.php / Stream.php
  Server_Request.php / Uploaded_File.php
  Message_Trait.php / Request_Trait.php     # Shared PSR-7 logic
  Request_Factory.php / Response_Factory.php / Uri_Factory.php
  Stream_Factory.php / Uploaded_File_Factory.php / Server_Request_Factory.php
  Response/
    Html_Response.php / Json_Response.php / Text_Response.php
    Xml_Response.php / Empty_Response.php / Redirect_Response.php
    Serializer.php / Array_Serializer.php   # HTTP response text serialization
  Request/
    Serializer.php / Array_Serializer.php   # HTTP request text serialization
  ServerRequestFilter/
    Filter_Using_X_Forwarded_Headers.php    # Trusted proxy / X-Forwarded-* header handling
    Do_Not_Filter.php
  Header_Security.php                       # Header injection prevention
  Callback_Stream.php / Relative_Stream.php
  functions/                                # SAPI-to-PSR-7 normalization helpers
    marshal_headers_from_sapi.php
    marshal_method_from_sapi.php
    normalize_uploaded_files.php
    parse_cookie_header.php
  Exception/                               # Typed exceptions for all error conditions
  Module.php / Config_Provider.php
```

## Key Design Decisions
- **Immutable value objects** — all PSR-7 message objects are immutable; `with*` methods return new instances. Internal state is never shared.
- **Typed response subclasses** — `Html_Response`, `Json_Response`, etc. pre-set `Content-Type` and encode the body automatically, reducing boilerplate in application code.
- **SAPI normalization** — the `functions/` helpers convert PHP's `$_SERVER`, `$_FILES`, etc. superglobals into PSR-7-compatible structures, handled once at request creation time.
- **Proxy header filtering** — `ServerRequestFilter` handles `X-Forwarded-For`, `X-Forwarded-Host`, and similar headers from trusted reverse proxies, with explicit allowlists to prevent header spoofing.
- **Header security** — `Header_Security` validates header values to prevent header injection attacks.

## Extension Points
- Use the PSR-17 factories with any framework that accepts `Psr\Http\Message\RequestFactoryInterface`.
- Register additional `ServerRequestFilter` implementations to handle non-standard proxy headers.
- Extend any response class to add project-specific `Content-Type` defaults.

## Dependency Flow
```
Server_Request_Factory::fromGlobals()
  ├─ marshal_headers_from_sapi($_SERVER)
  ├─ normalize_uploaded_files($_FILES)
  └─ ServerRequestFilter (X-Forwarded header processing)
       └─ Server_Request (immutable PSR-7 value object)

Response\Json_Response($data)
  └─ json_encode($data) → Stream
       └─ Response with Content-Type: application/json
```
