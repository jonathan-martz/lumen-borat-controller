### Installations


add to routes/web.php
```php
$router->get('{type}/packages.json', 'BoratController@packages');
$router->get('{type}/p/{vendor}/{module}.json', 'BoratController@package');

$router->get('{type}/packages.json', 'BoratController@packages');
$router->get('{type}/p/{vendor}/{module}.json', 'BoratController@package');
```
