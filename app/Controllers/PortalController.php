<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

/** Página inicial da intranet: cartões com os sistemas liberados para o usuário. */
final class PortalController
{
    public function inicio(): void
    {
        View::render('portal/inicio', [
            'titulo'   => 'Início',
            'sistemas' => Auth::sistemas(),
            'usuario'  => Auth::usuario(),
            'secao'    => 'portal',
        ]);
    }
}
