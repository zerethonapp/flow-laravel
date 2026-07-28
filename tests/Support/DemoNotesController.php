<?php

namespace Tests\Support;

class DemoNotesController
{
    public function index()
    {
        return response()->json([]);
    }

    public function store(DemoStoreRequest $request)
    {
        return response()->json($request->validated());
    }

    public function update(string $note, UnsafeRulesRequest $request)
    {
        return response()->json(['note' => $note]);
    }

    public function show(NoteModel $note)
    {
        return response()->json(['note' => $note->id]);
    }

    public function destroy(NoteModel $note)
    {
        return response()->json(['deleted' => $note->id]);
    }

    public function showPost(DemoPostModel $post)
    {
        return response()->json(['post' => $post->id]);
    }
}
