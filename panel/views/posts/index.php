<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><strong>Quản lý bài viết</strong></h1>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <select class="form-select" id="status-filter">
                        <option value="">Tất cả trạng thái</option>
                        <option value="public">Công khai</option>
                        <option value="pending">Chờ duyệt</option>
                        <option value="hidden">Đã ẩn</option>
                        <option value="deleted">Đã xóa</option>
                    </select>
                </div>
            </div>

            <table id="posts-table" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nội dung</th>
                        <th>Người đăng</th>
                        <th>Trạng thái</th>
                        <th>Tương tác</th>
                        <th>Ngày tạo</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
