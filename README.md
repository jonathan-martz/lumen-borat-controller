### Installations


add to routes/web.php
```php
$router->get('/packages.json', 'BoratController@packages');
$router->get('/p/{vendor}/{module}.json', 'BoratController@package');
```
