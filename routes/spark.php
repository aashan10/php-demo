<?php

use Elementary\Routing\Router;
use Elementary\Spark\SparkManager;
use Elementary\Http\Request;

// Spark message handling route with validation middleware
Router::middleware('spark')->group(function() {
    Router::post('/spark/message', function(Request $request, SparkManager $manager) {
        return $manager->handleRequest($request);
    });
});