@extends('admin.layouts.main')
@section('content')
     <section class="content">
         <div class="panel panel-primary">
             <div class="panel-heading">
                 <h3 class="panel-title">黑名单IP列表</h3>
             </div>
             <div class="panel-body">
                 @include('admin.black_list_ip.filter')

                 <table class="table table-bordered table-hover text-center">
                     <tr>
                         <th style="width: 10%">ID</th>
                         <th style="width: 20%">IP地址</th>
                         <th >备注</th>
                         <th  style="width: 20%">添加时间</th>
                         <th  style="width: 20%">最后更新时间</th>
                         <th  style="width: 20%">操作</th>
                     </tr>
                     @foreach($data as $item)
                         <tr>
                             <td>
                                 {{ $item->id }}
                             </td>
                             <td>
                                 {{ $item->ip }}
                             </td>
                             <td>
                                 {{ $item->remark }}
                             </td>
                             <td>
                                 {{ $item->created_at }}
                             </td>
                             <td>
                                 {{ $item->updated_at }}
                             </td>
                             <td>
                                 <a href="{{ route('black_list_ip.edit', ['id' => $item->getKey()]) }}" class="btn btn-primary btn-xs">修改</a>
                                 <button class="btn btn-danger btn-xs"
                                         data-url="{{route('black_list_ip.destroy', ['id' => $item->getKey()])}}"
                                         data-toggle="modal"
                                         data-target="#delete-modal"
                                 >
                                     删除
                                 </button>
                             </td>
                         </tr>
                     @endforeach
                 </table>
                 <div class="clearfix">
                     <div class="pull-left" style="margin: 0;">
                         <p>总共 <strong style="color: red">{{ $data->total() }}</strong> 条</p>
                     </div>
                 <div class="pull-right" style="margin: 0;">
                     {!! $data->render() !!}
                 </div>
                 </div>
             </div>
         </div>

     </section><!-- /.content -->
@endsection
@section("after.js")
     @include('admin.layouts.delete',['title'=>'操作提示','content'=>'你确定要删除这个IP吗?'])
@endsection