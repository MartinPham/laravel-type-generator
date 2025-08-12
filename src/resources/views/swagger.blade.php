<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="SwaggerUI" />
    <title>SwaggerUI</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.27.1/swagger-ui.css" />
</head>

<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5.27.1/swagger-ui-bundle.js" crossorigin></script>
<script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script>
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    window.onload = () => {
        window.ui = SwaggerUIBundle({
            url: '{{ $json_url }}',
            dom_id: '#swagger-ui',
            requestInterceptor: (request) => {
                const xsrfToken = getCookie('XSRF-TOKEN');
                if (xsrfToken) {
                    request.headers['X-XSRF-TOKEN'] = xsrfToken;
                }
                return request;
            },
        });
    };
</script>
</body>

</html>
