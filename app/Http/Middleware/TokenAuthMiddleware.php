<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokenAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $suap_token = $request->bearerToken();

        if (empty($suap_token)) {
            return response()->json([
                'error' => 'Não autorizado'
            ], 401);
        }

        $suap_data = null;

        if (cache()->has($suap_token)) {
            $suap_data = cache()->get($suap_token);
        } else {
            try {
                $resp = Http::withToken($suap_token)
                    ->acceptJson()
                    ->get('https://suap.ifrn.edu.br/api/v2/minhas-informacoes/meus-dados/')
                    ->getBody()
                    ->getContents();
            } catch (\Exception $e) {
                Log::error('Erro ao acessar o SUAP: ' . $e->getMessage());

                return response()->json([
                    'error' => 'Erro ao acessar o SUAP',
                ], 500);
            }

            $json = json_decode(
                $resp,
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );

            $suap_data = [
                'nome' => $json['nome_usual'],
                'matricula' => $json['matricula'],
            ];

            cache()->put($suap_token, $suap_data);
        }

        $request->attributes->set('usuario', $suap_data);

        return $next($request);
    }


    /**
     * Gets user data from SUAP
     *
     * @param string $suap_token JWT token generated by SUAP in the URI
     * https://suap.ifrn.edu.br/api/v2/autenticacao/token/
     *
     * @return array User data in SUAP
     */
    private function getUserDataFromSUAP($suap_token): array {

        $res = json_decode(
            Http::withToken($suap_token)
                ->acceptJson()
                ->get('https://suap.ifrn.edu.br/api/v2/minhas-informacoes/meus-dados/')
                ->getBody()->getContents(),
            associative: true
        );

        $data = [
            'nome' => $res['nome_usual'],
            'matricula' => $res['matricula'],
        ];

        return $data;
    }
}
