<?php
namespace SwaggerGen\Http;

use SwaggerGen\Model\Model;
use SwaggerGen\Message\Request;

interface Client {

	/**
	 * @param Request $request
	 * @param string[] $response_models
	 * @return Model|Model[]
	 */
	public function make(Request $request, array $response_models);
}
