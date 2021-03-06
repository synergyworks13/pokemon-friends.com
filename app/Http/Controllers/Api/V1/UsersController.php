<?php

namespace template\Http\Controllers\Api\V1\Users;

use Illuminate\Http\Request;
use Laravel\Cashier\Subscription;
use template\Domain\Users\Profiles\Profile;
use template\Domain\Users\Users\Repositories\UsersRepositoryEloquent;
use template\Infrastructure\Contracts\Controllers\ControllerAbstract;
use template\Domain\Users\Users\Transformers\UserTransformer;
use template\Domain\Users\Users\User;

class UsersController extends ControllerAbstract
{

    /**
     * @var UsersRepositoryEloquent|null
     */
    protected $rUsers = null;

    /**
     * UserController constructor.
     *
     * @param UsersRepositoryEloquent $rUsers
     */
    public function __construct(UsersRepositoryEloquent $rUsers)
    {
        $this->rUsers = $rUsers;
    }

    /**
     * Get User.
     *
     * @param User $user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user)
    {
        $user = (new UserTransformer())->transform($user);

        return response()->json($user);
    }

    /**
     * Get the authenticated User.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return $this->show($request->user());
    }

    /**
     * Get user QR code image.
     *
     * @param Request $request
     * @param User $user
     *
     * @return \Illuminate\Http\Response|mixed
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function qr(Request $request, User $user)
    {
        if (!$user || $user->deleted_at || !$user->profile->friend_code) {
            abort(404);
        }

        if (!$user->profile->hasMedia('trainer')) {
            return $user
                ->profile
                ->addMediaFromUrl(
                    'https://api.qrserver.com/v1/create-qr-code/'
                    . "?size=300x300&format=png&data={$user->profile->friend_code}"
                )
                ->setName($user->profile->friend_code)
                ->setFileName("{$user->profile->friend_code}.png")
                ->toMediaCollection('trainer')
                ->toResponse($request);
        }

        return $user->profile->getMedia('trainer')->first()->toResponse($request);
    }

    public function channels()
    {

//        $subscriptions = Profile::whith('subscription')
//            ->addSelect([
//                'subscription' => Subscription::whereColumn('ends_at', null)
//                    ->whereColumn('trial_ends_at')
//                    ->whereColumn('trial_ends_at')
//            ]);

        return response()->json(['blazed_css']);
    }
}
