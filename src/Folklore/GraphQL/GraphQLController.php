<?php namespace Folklore\GraphQL;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GraphQLController extends Controller
{
    public function query(Request $request, $schema = null)
    {
        $isBatch = !$request->has('query');
        $inputs = $request->all();

        if (!$schema) {
            $schema = config('graphql.schema');
        }

        if (!$isBatch) {
            $data = $this->executeQuery($schema, $inputs);
        } else {
            $data = [];
            foreach ($inputs as $input) {
                $data[] = $this->executeQuery($schema, $input);
            }
        }


        $errors = !$isBatch ? array_get($data, 'errors', []) : [];

        if (!$this->containsMatchedError($errors, 'Unauthenticated')) {
            return $this->queryResponse($data, 401);
        }

        if (!$this->containsMatchedError($errors, 'Unauthorized')) {
            return $this->queryResponse($data, 403);
        }

        return $this->queryResponse($data);
    }

    private function containsMatchedError($errors, $needle)
    {
        return array_reduce($errors, function ($authorized, $error) use ($needle) {
            return !$authorized || array_get($error, 'message') === $needle ? false : true;
        }, true);
    }

    private function queryResponse($data, $statusCode = 200)
    {
        $headers = config('graphql.headers', []);
        $options = config('graphql.json_encoding_options', 0);

        return response()->json($data, $statusCode, $headers, $options);
    }

    public function graphiql(Request $request, $schema = null)
    {
        $view = config('graphql.graphiql.view', 'graphql::graphiql');
        return view($view, [
            'schema' => $schema,
        ]);
    }

    protected function executeQuery($schema, $input)
    {
        $variablesInputName = config('graphql.variables_input_name', 'variables');
        $query = array_get($input, 'query');
        $variables = array_get($input, $variablesInputName);
        if (is_string($variables)) {
            $variables = json_decode($variables, true);
        }
        $operationName = array_get($input, 'operationName');
        $context = $this->queryContext($query, $variables, $schema);
        return app('graphql')->query($query, $variables, [
            'context' => $context,
            'schema' => $schema,
            'operationName' => $operationName
        ]);
    }

    protected function queryContext($query, $variables, $schema)
    {
        try {
            return app('auth')->user();
        } catch (\Exception $e) {
            return null;
        }
    }
}
