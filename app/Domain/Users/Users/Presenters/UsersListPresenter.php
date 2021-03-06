<?php

namespace template\Domain\Users\Users\Presenters;

use template\Infrastructure\Contracts\Presenters\PresenterAbstract;
use template\Domain\Users\Users\Transformers\UsersListTransformer;

class UsersListPresenter extends PresenterAbstract
{

    /**
     * Transformer
     *
     * @return \League\Fractal\TransformerAbstract
     */
    public function getTransformer()
    {
        return new UsersListTransformer();
    }
}
