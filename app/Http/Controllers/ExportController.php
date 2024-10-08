<?php

namespace App\Http\Controllers;

use App\Models\TodoList;
use Google\Client;
use Google\Exception as GoogleException;
use Google\Service\Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;

class ExportController extends Controller
{
	protected function CreateCSVFile(): false|string{
		$allTasks = TodoList::with(['tasks' => function ($query) {
			$query->select(['list_id', 'title', 'is_done'])
				->orderBy('is_done', 'asc')
				->orderBy('priority', 'desc');
		}])->select('id', 'name', 'user_id')
			->where('user_id', Auth::id())
			->get();

		$csvData = fopen('php://temp', 'r+');
		foreach ($allTasks as $list) {
			fputcsv($csvData, [$list->name, '']);
			foreach($list->tasks as $task){
				fputcsv($csvData, [$task->title, $task->is_done ? 'completed' : 'incomplete']);
			}
		}

		rewind($csvData);
		$csvContent = stream_get_contents($csvData);
		fclose($csvData);
		return $csvContent;
	}

	public function exportToDrive(): RedirectResponse{
		if(request('error')){
			Session::flash('google_drive', [
				'type' => 'error',
				'msg' => 'Access to Google Drive is cancelled! Your list is not exported.'
			]);
		}else if(request('code')){
			try {
				$client = new Client();
				$client->setClientId(config('services.google_drive.client_id'));
				$client->setClientSecret(config('services.google_drive.client_secret'));
				$client->setRedirectUri(config('app.url') . '/export/progress');

				$client->fetchAccessTokenWithAuthCode(request('code'));

				$user = Auth::user();
				$file_id = $user->google_drive_backup_file_id ?? '';
				$post_body = new Drive\DriveFile([
					'name' => 'backup_from_plantodo_ir.csv'
				]);
				$post_options = [
					'data' => $this->CreateCSVFile(),
					'mimeType' => 'text/csv',
					'uploadType' => 'multipart',
					'fields' => 'id'
				];

				$driveService = new Drive($client);

				if($file_id){
					$file = $driveService->files->update($file_id, $post_body, $post_options);
				}else{
					$file = $driveService->files->create($post_body, $post_options);
				}


				if($file->id){
					$file->id != $file_id && $user->update([
						'google_drive_backup_file_id' => $file->id
					]);
					Session::flash('google_drive', [
						'type' => 'success',
						'msg' => 'Your tasks are exported to your Google Drive account.'
					]);
				}else{
					Session::flash('google_drive', [
						'type' => 'error',
						'msg' => 'Failed to upload the exported file.'
					]);
				}
			} catch(GoogleException $e) {
				if(App::hasDebugModeEnabled()){
					dd($e);
				}else{
					Session::flash('google_drive', [
						'type' => 'error',
						'msg' => 'Something went wrong while exporting your data to Google Drive.'
					]);
				}
			}
		}else{
			Session::flash('google_drive', [
				'type' => 'error',
				'msg' => 'Something went wrong while exporting your data to Google Drive.'
			]);
		}

		return redirect()->away(route('todo-list'));
	}

	public function getGoogleDriveAccess(): RedirectResponse{
		$client = new Client();
		$client->setClientId(config('services.google_drive.client_id'));
		$client->addScope(Drive::DRIVE_FILE);
		$client->setRedirectUri(config('app.url') . '/export/progress');
		return redirect()->away($client->createAuthUrl());
	}

	public function downloadCSVExport(): \Illuminate\Http\Response{
		return Response::make($this->CreateCSVFile(), 200, [
			'Content-Type' => 'text/csv',
			'Content-Disposition' => 'attachment; filename="backup_from_plantodo_ir.csv"',
		]);
	}
}
