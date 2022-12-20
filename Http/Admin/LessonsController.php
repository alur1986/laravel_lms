<?php

namespace App\Http\Controllers\Backend\Admin;


use App\Models\Course;
use App\Models\CourseTimeline;
use App\Models\Lesson;
use App\Models\Category;
use App\Models\Media;
use App\Models\Test;
use App\Models\Auth\User;
use App\Models\Auth\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLessonsRequest;
use App\Http\Requests\Admin\UpdateLessonsRequest;
use App\Http\Controllers\Traits\FileUploadTrait;
use Yajra\DataTables\Facades\DataTables;

class LessonsController extends Controller
{
    use FileUploadTrait;

    /**
     * Display a listing of Lesson.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
    
       if (!Gate::allows('lesson_access')) {
            return abort(401);
        }

       $categories = Category::get()->pluck('name', 'id')->prepend('Please select', '');

        $teachers = \App\Models\Auth\User::role('teacher')->get();

        return view('backend.lessons.index', compact('categories'));
    }

    /**
     * Display a listing of Lessons via ajax DataTable.
     *
     * @return \Illuminate\Http\Response
     */
    public function getData(Request $request)
    {
        $has_view = false;
        $has_delete = false;
        $has_edit = false;
        $lessons = "";

        $category_id=intval($request->category_id);


          if (request('show_deleted') == 1) {
            if (!Gate::allows('category_delete')) {
                return abort(401);
            }
            $lessons = Lesson::query()->onlyTrashed()
                ->orderBy('created_at', 'desc');
        } else {
            $lessons = Lesson::query()->where('category_id', '=',$category_id)->orderBy('created_at', 'desc');
        }




        if (auth()->user()->can('lesson_view')) {
            $has_view = true;
        }
        if (auth()->user()->can('lesson_edit')) {
            $has_edit = true;
        }
        if (auth()->user()->can('lesson_delete')) {
            $has_delete = true;
        }

        return DataTables::of($lessons)
            ->addIndexColumn()
            ->addColumn('actions', function ($q) use ($has_view, $has_edit, $has_delete, $request) {
                $view = "";
                $edit = "";
                $delete = "";
                if ($request->show_deleted == 1) {
                    return view('backend.datatable.action-trashed')->with(['route_label' => 'admin.lessons', 'label' => 'id', 'value' => $q->id]);
                }
                if ($has_view) {
                    $view = view('backend.datatable.action-view')
                        ->with(['route' => route('admin.lessons.show', ['lesson' => $q->id])])->render();
                }
                if ($has_edit) {
                    $edit = view('backend.datatable.action-edit')
                        ->with(['route' => route('admin.lessons.edit', ['lesson' => $q->id])])
                        ->render();
                    $view .= $edit;
                }

                if ($has_delete) {
                    $delete = view('backend.datatable.action-delete')
                        ->with(['route' => route('admin.lessons.destroy', ['lesson' => $q->id])])
                        ->render();
                    $view .= $delete;
                }

                if (auth()->user()->can('test_view')) {
                    if ($q->test != "") {
                        $view .= '<a href="' . route('admin.tests.index', ['lesson_id' => $q->id]) . '" class="btn btn-success btn-block mb-1">' . trans('labels.backend.tests.title') . '</a>';
                    }
                }


                  $view .= '<a class="btn btn-warning mb-1" href="' . route('admin.lessons.index', ['category_id' => $q->id]) . '">' . trans('labels.backend.lessons.title') . '</a>';


                return $view;
            })
            ->editColumn('category', function ($q) {
                return ($q->category) ? $q->category->name : 'N/A';
            })
           
            ->editColumn('published', function ($q) {
                return ($q->published == 1) ? "Yes" : "No";
            })
            ->rawColumns(['title', 'actions'])
            ->make(true);


            
    }

    /**
     * Show the form for creating new Lesson.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('lesson_create')) {
            return abort(401);
        }
        
         $categories = Category::get();
        
         $teachers = User::role('teacher')->get();

        return view('backend.lessons.create', compact('categories', 'teachers'));
    }

    /**
     * Store a newly created Lesson in storage.
     *
     * @param  \App\Http\Requests\StoreLessonsRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLessonsRequest $request)
    {
        if (!Gate::allows('lesson_create')) {
            return abort(401);
        }

 
       $lesson = new Lesson();

       if (($request->slug != "") || $request->slug != null) {
            $lesson->slug = str_slug($request->title);
            $lesson->save();
        }


     
       if (($request->title != "") || $request->title != null) {
            $lesson->title =$request->title;
            $lesson->save();
        }



       
       if (($request->short_description != "") || $request->short_description != null) {
            $lesson->short_description=$request->short_description;
            $lesson->save();
        }


       
       if (($request->description != "") || $request->description != null) {
            $lesson->description=$request->description;
            $lesson->save();
        }



        

        if (($request->category_id != "") || $request->category_id != null) {
            $lesson->category_id=intval($request->category_id);
            $lesson->save();
        }
 
    


        if (($request->teacher_profile_id != "") || $request->teacher_profile_id != null) {
            $lesson->teacher_profile_id=intval($request->teacher_profile_id);
            $lesson->save();
        }
 

 
     if (($request->published != "") || $request->published != null) {
            $lesson->published=intval($request->published);
            $lesson->save();
        }


 


        //Saving  videos
        if ($request->media_type != "") {
            $model_type = Lesson::class;
            $model_id = $lesson->id;
            $size = 0;
            $media = '';
            $url = '';
            $video_id = '';
            $name = $lesson->title . ' - video';

            if (($request->media_type == 'youtube') || ($request->media_type == 'vimeo')) {
                $video = $request->video;
                $url = $video;
                $video_id = array_last(explode('/', $request->video));
                $media = Media::where('url', $video_id)
                    ->where('type', '=', $request->media_type)
                    ->where('model_type', '=', 'App\Models\Lesson')
                    ->where('model_id', '=', $lesson->id)
                    ->first();
                $size = 0;
            } elseif ($request->media_type == 'upload') {
                if (\Illuminate\Support\Facades\Request::hasFile('video_file')) {
                    $file = \Illuminate\Support\Facades\Request::file('video_file');
                    $filename = time() . '-' . $file->getClientOriginalName();
                    $size = $file->getSize() / 1024;
                    $path = public_path() . '/storage/uploads/';
                    $file->move($path, $filename);

                    $video_id = $filename;
                    $url = asset('storage/uploads/' . $filename);
                   
                    $media = Media::where('type', '=', $request->media_type)
                        ->where('model_type', '=', 'App\Models\Lesson')
                        ->where('model_id', '=', $lesson->id)
                        ->first();
                }
            } elseif ($request->media_type == 'embed') {
                $url = $request->video;
                 $lesson->video_url=$url;
                $lesson->save();
                $filename = $lesson->title . ' - video';
            }

            if ($media == null) {
                $media = new Media();
                $media->model_type = $model_type;
                $media->model_id = $model_id;
                $media->name = $name;
                $media->url = $url;
                $media->type = $request->media_type;
                $media->file_name = $video_id;
                $media->size = 0;
                $media->save();

              $lesson->video_url=$url;
              $lesson->save();

            }
        }

      
         return redirect()->route('admin.lessons.step2');

      
       // return redirect()->route('admin.lessons.index', ['category_id' => $request->category_id])->withFlashSuccess(__('alerts.backend.general.created'));
    }


    /**
     * Show the form for editing Lesson.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('lesson_edit')) {
            return abort(401);
        }
        $videos = '';
        

         $categories = Category::get();
        
         $teachers = User::role('teacher')->get();

        $lesson = Lesson::with('media')->findOrFail($id);
        if ($lesson->media) {
            $videos = $lesson->media()->where('media.type', '=', 'YT')->pluck('url')->implode(',');
        }

        return view('backend.lessons.edit', compact('lesson', 'categories', 'videos','teachers'));
    }

    /**
     * Update Lesson in storage.
     *
     * @param  \App\Http\Requests\UpdateLessonsRequest $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLessonsRequest $request, $id)
    {
        if (!Gate::allows('lesson_edit')) {
            return abort(401);
        }

        $slug = "";
  
        $lesson = Lesson::findOrFail($id);

       if (($request->slug != "") || $request->slug != null) {
            $lesson->slug = str_slug($request->title);
            $lesson->save();
        }


     
       if (($request->title != "") || $request->title != null) {
            $lesson->title =$request->title;
            $lesson->save();
        }



       
       if (($request->short_description != "") || $request->short_description != null) {
            $lesson->short_description=$request->short_description;
            $lesson->save();
        }


       
       if (($request->description != "") || $request->description != null) {
            $lesson->description=$request->description;
            $lesson->save();
        }


        if (($request->category_id != "") || $request->category_id != null) {
            $lesson->category_id=intval($request->category_id);
            $lesson->save();
        }
 
    


        if (($request->teacher_profile_id != "") || $request->teacher_profile_id != null) {
            $lesson->teacher_profile_id=intval($request->teacher_profile_id);
            $lesson->save();
        }

     if (($request->published != "") || $request->published != null) {
            $lesson->published=intval($request->published);
            $lesson->save();
        }


  

        //Saving  videos
        if ($request->media_type != "") {
            $model_type = Lesson::class;
            $model_id = $lesson->id;
            $size = 0;
            $media = '';
            $url = '';
            $video_id = '';
            $name = $lesson->title . ' - video';
            $media = $lesson->mediavideo;
            if ($media == "") {
                $media = new  Media();
            }
            if ($request->media_type != 'upload') {
                if (($request->media_type == 'youtube') || ($request->media_type == 'vimeo')) {
                    $video = $request->video;
                    $url = $video;
                    $video_id = array_last(explode('/', $request->video));
                    $size = 0;
                } elseif ($request->media_type == 'embed') {
                    $url = $request->video;
                    $filename = $lesson->title . ' - video';
                }
                $media->model_type = $model_type;
                $media->model_id = $model_id;
                $media->name = $name;
                $media->url = $url;
                $media->type = $request->media_type;
                $media->file_name = $video_id;
                $media->size = 0;
                $media->save();
            }

            if ($request->media_type == 'upload') {
                if (\Illuminate\Support\Facades\Request::hasFile('video_file')) {
                    $file = \Illuminate\Support\Facades\Request::file('video_file');
                    $filename = time() . '-' . $file->getClientOriginalName();
                    $size = $file->getSize() / 1024;
                    $path = public_path() . '/storage/uploads/';
                    $file->move($path, $filename);

                    $video_id = $filename;
                    $url = asset('storage/uploads/' . $filename);

                    $media = Media::where('type', '=', $request->media_type)
                        ->where('model_type', '=', 'App\Models\Lesson')
                        ->where('model_id', '=', $lesson->id)
                        ->first();

                    if ($media == null) {
                        $media = new Media();
                    }
                    $media->model_type = $model_type;
                    $media->model_id = $model_id;
                    $media->name = $name;
                    $media->url = $url;
                    $media->type = $request->media_type;
                    $media->file_name = $video_id;
                    $media->size = 0;
                    $media->save();
                }
            }
        }

       return redirect()->route('admin.lessons.step2');

       // return redirect()->route('admin.lessons.index', ['category_id' => $request->category_id])->withFlashSuccess(__('alerts.backend.general.updated'));
    }



    /**
     * Show the form for editing Lesson.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function step2()
    {
       

        return view('backend.lessons.step2');
    }
 








    /**
     * Display Lesson.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!Gate::allows('lesson_view')) {
            return abort(401);
        }
        

        //$lesson = findOrFail($id);

        //$category_id = $lesson->category_id;

        //$teacher_profile_id =$lesson->teacher_profile_id; 

         $categories = Category::get();
        
        $teachers = User::role('teacher')->get();

        $tests = Test::where('lesson_id', $id)->get();

        $lesson = Lesson::findOrFail($id);


        return view('backend.lessons.show', compact('lesson', 'tests', 'categories','teachers'));
    }


    /**
     * Remove Lesson from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('lesson_delete')) {
            return abort(401);
        }

        $lesson = Lesson::findOrFail($id);;
        $lesson->delete();

        return back()->withFlashSuccess(__('alerts.backend.general.deleted'));
    }

    /**
     * Delete all selected Lesson at once.
     *
     * @param Request $request
     */
    public function massDestroy(Request $request)
    {
        if (!Gate::allows('lesson_delete')) {
            return abort(401);
        }
        if ($request->input('ids')) {
            $entries = Lesson::whereIn('id', $request->input('ids'))->get();

            foreach ($entries as $entry) {
                $entry->delete();
            }
        }
    }


    /**
     * Restore Lesson from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function restore($id)
    {
        if (!Gate::allows('lesson_delete')) {
            return abort(401);
        }
        $lesson = Lesson::onlyTrashed()->findOrFail($id);
        $lesson->restore();

        return back()->withFlashSuccess(trans('alerts.backend.general.restored'));
    }

    /**
     * Permanently delete Lesson from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function perma_del($id)
    {
        if (!Gate::allows('lesson_delete')) {
            return abort(401);
        }
        $lesson = Lesson::onlyTrashed()->findOrFail($id);

        if (File::exists(public_path('/storage/uploads/'.$lesson->lesson_image))) {
            File::delete(public_path('/storage/uploads/'.$lesson->lesson_image));
            File::delete(public_path('/storage/uploads/thumb/'.$lesson->lesson_image));
        }

        $timelineStep = CourseTimeline::where('model_id', '=', $id)
            ->where('course_id', '=', $lesson->course->id)->first();
        if ($timelineStep) {
            $timelineStep->delete();
        }

        $lesson->forceDelete();



        return back()->withFlashSuccess(trans('alerts.backend.general.deleted'));
    }
}
