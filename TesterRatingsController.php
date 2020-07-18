<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Rating;
use App\Http\Resources\RatingResource;
use App\Models\User;

class TesterRatingsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function project($testerId, $projectId)
    {
        $project = Project::findOrFail($projectId);
        $tester = User::findOrFail($testerId);
        if (!$project->testers->contains($tester)) {
            return abort(422, 'Tester does not exist for this project');
        }
        if (!(auth()->user()->isAdmin() || intval($testerId) === auth()->user()->id)) {
            abort(403, 'You can not rate for this tester for this project');
        }
        $rated = $project->ratings()->whereHas('subjectTester', function ($q) use ($testerId) {
            $q->where('id', $testerId);
        })->get();
        $ratings = collect(['content', 'gameplay', 'multimedia'])->map(function ($collectionName) use ($rated, $project, $tester) {
            $ratedItem = $rated->first(function ($item) use ($collectionName) {
                return $item->collection_name === $collectionName;
            });
            $requestData = request($collectionName);
            if ($requestData) {
                if ($ratedItem) {
                    $ratedItem->score = $requestData['score'];
                    $ratedItem->suggestion = $requestData['suggestion'];
                    $ratedItem->save();
                    return $ratedItem;
                }
                return $project->giveRatings($requestData['score'], $requestData['suggestion'], $collectionName)->forTester($tester);
            }
            return null;
        });
        return RatingResource::collection($ratings);
    }

    public function myRatingsForProject($projectId)
    {
        $project = Project::findOrFail($projectId);
        $tester = auth()->user();
        if (!$project->testers->contains($tester)) {
            return abort(422, 'Tester does not exist for this project');
        }
        $ratings = $project->ratings()->whereHas('subjectTester', function ($q) use ($tester) {
            $q->where('id', $tester->id);
        })->get();
        return RatingResource::collection($ratings);
    }
}
