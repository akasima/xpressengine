<div class="form-group">
    <select name="{{$config->get('id') . 'ItemId'}}" class="xe-form-control">
        <option value="">{{xe_trans($config->get('label'))}}</option>
        @foreach ($items as $item)
            <option value="{{$item->id}}" {{$itemId == $item->id ? 'selected="selected"' : ''}}>{{$item->word}}</option>
        @endforeach
    </select>
</div>