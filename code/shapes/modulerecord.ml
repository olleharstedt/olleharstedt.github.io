module type SHAPE = sig
    type t
    type s = {
        data : t;
        area : s -> int
    }
    val make : unit -> s
end

module Point : SHAPE = struct
    type t = {x : int; y : int}
    type s = {
        data : t;
        area : s -> int
    }

    let make () =
        {
            data = {x = 1; y = 2};
            area = fun p -> 0
        }
end

module Rect : SHAPE = struct
    type t = {bottom_left : Point.s; top_right : Point.s}
    type s = {
        data : t;
        area : s -> int
    }

    let make () =
        {
            data = {bottom_left = Point.make (); top_right = Point.make ()};
            area = fun p -> 1
        }
end

type 'a areable = { data : 'a ; area : 'a -> int }
type any_shape = Any : (module SHAPE with type t = 'a) -> any_shape
(*type any_shape = Any : 'a -> any_shape*)

let l = [Any (Rect.make ()); Any (Point.make ())]
let areas = List.map (fun (Any s) -> s.area s) l
