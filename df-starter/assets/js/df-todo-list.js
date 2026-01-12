let draggedTask = null;

// function to recreate the task table, in case a collum needs to be added or removed
function recreate_task_table() {
    fetch(myplugin_ajax.ajax_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
            action: "recreate_tasks_table"
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            console.log(data.data.message);
            alert(data.data.message);
        } else {
            console.error(data.data.message);
            alert("Error: " + data.data.message);
        }
    });
}

// drag and drop functions
function handleDragStart(event) {
    draggedTask = event.target;
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/html", event.target.outerHTML);
}

function handleDragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = "move";
}

function handleDrop(event) {
    event.preventDefault();

    if (!draggedTask) {
        return;
    }

    const parent = draggedTask.parentElement;
    const dropZone = event.target.closest('.project');
    if (!dropZone) {
        return;
    }

    const dropTarget = event.target.closest('.task');

    if (dropTarget && dropTarget !== draggedTask) {
        draggedTask.remove();
        dropZone.insertBefore(draggedTask, dropTarget);

        update_project_order(dropZone);
        update_project_task_order(dropZone);
        if (parent.id !== dropZone.id) {
            update_project_order(parent);
            update_project_task_order(parent);
        }

    } else {
        dropZone.appendChild(draggedTask);
    }
}   

function update_project_order(project)
{
    if (!project) {
        console.log("The project element isn't defined");
        return;
    }

    let not_task_offset = 0;
    for (let i = 0; i < project.children.length; i++) {

        const child = project.children[i];
        if (child.classList.contains('task')) {
            child.dataset.order = i - not_task_offset;
        } else {
            not_task_offset++;
        }                    
    }
}

// database functions
function add_project(event) {
    const project_name = document.getElementById("new-project-name").value;
    if (!project_name)
        alert("Pas de nom de projet");

    fetch(myplugin_ajax.ajax_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
            action: "add_todo_project",
            project_name: project_name,
            is_done: 0
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            let project_div = document.createElement('div');
            let project_name_header  = document.createElement('h3');
            let project_delete_button = document.createElement('button');
            let add_project_div = document.createElement('div');
            let add_project_input = document.createElement('input');
            let add_project_button = document.createElement('button');

            project_div.dataset.id = data.data.last_id;
            project_div.id = "project_" + data.data.last_id;
            project_div.classList.add("project");
            project_div.ondragover = function(event) { handleDragOver(event) };
            project_div.ondrop = function(event) { handleDrop(event) };
            
            project_name_header.textContent = escape_html(project_name);

            project_delete_button.classList.add("delete-project-btn");
            project_delete_button.onclick = function() { delete_project(data.data.last_id); }
            project_delete_button.textContent = "Delete";

            add_project_div.classList.add("add-section");

            add_project_input.type = "text";
            add_project_input.id = "new-task-name-" + data.data.last_id;
            add_project_input.placeholder = "Nom de la tâche";

            add_project_button.dataset.id = "" + data.data.last_id;
            add_project_button.onclick = function(event) { add_task(event); };
            add_project_button.textContent = "Ajouter tâche";

            add_project_div.append(add_project_input);
            add_project_div.append(add_project_button);

            project_div.append(project_name_header);
            project_div.append(project_delete_button);
            project_div.append(add_project_div);

            document.getElementById("task-container").append(project_div);
        } else {
            console.error(data.data.message);
            alert("Error: " + data.data.message);
        }
    });
}

function add_task(event) {
    let project_id = event.target.dataset.id;
    let task_element = document.getElementById("new-task-name-" + project_id);
    const task_name = task_element.value.trim();

    if (!task_name) {
        alert("Veuillez entrer un nom de tâche.");
        return;
    }

    fetch(myplugin_ajax.ajax_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
            action: "add_todo_task",
            project_id: project_id,
            task: task_name,
            is_done: 0
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            let task_div = document.createElement('div');
            let task_name_span  = document.createElement('span');
            let task_toggle_span  = document.createElement('span');
            let task_delete_button = document.createElement('button');

            task_div.id = "task-" + data.data.last_id;
            task_div.dataset.id = data.data.last_id;
            task_div.dataset.order = data.data.order;
            task_div.classList.add("task"); 
            task_div.draggable = "true";
            task_div.ondragstart = function(event) { handleDragStart(event) };
            
            task_name_span.textContent = escape_html(task_name);
            
            task_toggle_span.classList.add("toggle-btn");
            task_toggle_span.dataset.id = data.data.last_id;
            task_toggle_span.onclick = function() { toggleDone(this); };
            
            task_delete_button.classList.add("delete-task-btn");
            task_delete_button.onclick = function() { delete_task(data.data.last_id) }
            task_delete_button.textContent = '×';

            task_div.append(task_name_span);
            task_div.append(task_toggle_span);
            task_div.append(task_delete_button);

            document.getElementById("project_" + project_id).append(task_div);
        } else {
            alert(data.data.message);
        }
    });
}

function toggleDone(el) {
    let isDone = el.classList.contains('done');
    let done = isDone ? 0 : 1;
    let task_id = el.dataset.id;
    fetch(myplugin_ajax.ajax_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
            action: "update_todo_task_status",
            task_id: task_id,
            is_done: done
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            el.classList.toggle('done');
        } else {
            alert("An error occured when changing the status of a task");
        }
    });
}

function update_project_task_order(project)
{
    if (!project) {
        console.log("The project element isn't defined");
        return;
    }

    let project_id = project.dataset.id;
    if (!project_id) {
        console.log("Project id not found when updating order");
        return;
    }

    const data = {};

    for (let i = 0; i < project.children.length; i++) {

        const child = project.children[i];
        if (child.classList.contains('task')) {
            data[child.dataset.id] = child.dataset.order;
        }                 
    }

    let json_data = JSON.stringify(data);

    fetch(myplugin_ajax.ajax_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
            action: "update_todo_order",
            project_id: project_id,
            json_data: json_data
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // success case, e.g.:
            console.log(data.data.message);
        } else {
            const errorMsg = data.data && data.data.message ? data.data.message : 'Unknown error';
            alert("An error occured when trying to update the order of the tasks with the message: " + errorMsg);
        }
    });
}

function delete_project(project_id)
{
    ajax_call(
        new URLSearchParams({ action: "delete_todo_project", project_id: project_id }),
        data => {
            if (data.success) {
                const element = document.getElementById('project_' + project_id);
                if (element) {
                  element.remove();
                }
            } else {
                alert("An error occured when deleting a project");
            }
        }
    );
}

function delete_task(task_id)
{
    ajax_call( 
        new URLSearchParams({ action: "delete_todo_task", task_id: task_id }),
        data => { 
            if (data.success) {
                const element = document.getElementById('task-' + task_id);
                if (element) {
                  element.remove();
                }
            } else {
                alert("An error occured when deleting a task");
            }
        }
    );
}

function ajax_call(body, callback)
{
    fetch(myplugin_ajax.ajax_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: body
    })
    .then(res => res.json())
    .then(data => {
        callback(data);
    });
}

// helper functions
function escape_html(text)
{
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}