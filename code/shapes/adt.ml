type point = {
  x : int;
  y : int;
}

type rectangle = {
  bottom_left : point;
  top_right : point;
}

type shape =
    | Point of point
    | Rectangle of rectangle

let area_of_shape (s : shape) : int =
  match s with
    | Point p -> 0
    | Rectangle {bottom_left; top_right} -> 1

let l = [
    Point {x = 1; y = 2};
    Rectangle {bottom_left = {x = 2; y = 3}; top_right = {x = 3; y = 4}};
]

let areas = List.map (fun s -> area_of_shape s) l
